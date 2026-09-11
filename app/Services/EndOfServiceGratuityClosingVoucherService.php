<?php

namespace App\Services;

use App\Enums\PayslipLineKind;
use App\Enums\SalaryComponent;
use App\Models\EosgClosingVoucher;
use App\Models\EosgClosingVoucherLine;
use App\Models\Party;
use App\Models\PayslipLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The annual EOSG closing journal voucher, generated and saved once a year at
 * 31/12 — not recomputed on the fly, unlike the monthly Salaries voucher.
 *
 * Every payroll run still computes and stores that month's gratuity accrual on
 * its payslips (`PayslipLine::EMPLOYER_COST`, via `PayrollService::accrueGratuity()`)
 * — the monthly figure stays accurate under mid-year salary changes because it is
 * a telescoping difference of cumulative entitlement. What this service does is
 * sum a calendar year's worth of those stored lines into one closing entry and
 * SAVE it (`EosgClosingVoucher` + `EosgClosingVoucherLine`), rather than the
 * monthly Salaries voucher (`PayrollJournalVoucherService`) posting a slice of
 * it every month.
 *
 *   Dr  Employer EOSG accrual expense   — itemised per employee
 *     Cr  EOSG provision (liability)    — the year's total, in one line
 *
 * Saving it is deliberate: once a year is closed, a payroll correction made
 * months later must not silently reshape what accounting already posted.
 * `generate()` is the only thing that changes the saved figures, and it
 * replaces the year's row and lines wholesale rather than patching them.
 *
 * An employee whose profile has `is_eosg_applicable` set to false never accrues
 * anything in the first place (`PayrollService::accrueGratuity()` returns 0 for
 * them), so excluding them again here is a defensive second check rather than
 * the only one.
 *
 * A year is only trusted to its monthly payslips when EVERY month that
 * employee was actually employed in has an EOSG payslip line — a partial year
 * (say, payroll only ran from August, or three months are missing because a
 * run was deleted) is not summed as-is, since that would silently understate
 * the year. Whenever coverage is incomplete — including a year with no
 * payslips at all, typically because the office had not yet started running
 * payroll through this system — `generate()` discards whatever partial figure
 * exists and falls back to a synthetic annual figure for exactly that
 * employee in exactly that year, computed the same way as a month's accrual
 * (`EndOfServiceGratuityService::accrualBetween()`, spanning the whole
 * calendar year instead of a month), using whichever basic salary was on
 * record at the year's end (`PayrollService::salaryAt()`). Nothing here
 * assumes the salary was constant for the whole year — only that the
 * year-end figure is the best available estimate when the monthly record is
 * missing or incomplete.
 *
 * An employee's `opening_eosg_balance` — gratuity entered once by HR/Finance
 * for service this system has no record of at all — is added on top of
 * whichever voucher is generated FIRST for that employee (checked by whether
 * any `EosgClosingVoucherLine` already exists for them in a different year),
 * never again after that. Which year counts as "first" is whichever is
 * generated first in practice, not necessarily the chronologically earliest —
 * the same one-time-top-up rule `LeaveEntitlementService` applies to an
 * opening leave balance.
 *
 * Every line also carries its OPENING and CLOSING cumulative balance, not
 * just the year's movement — a proper provision rollforward, not a bare
 * figure. Closing balance is always `opening + this year's movement`, and
 * opening balance is whichever year was generated immediately before this one
 * for that employee (its saved closing balance), chained year over year;
 * for whichever year is generated FIRST for an employee, there is no prior
 * year to chain from, so opening balance is computed fresh from their service
 * dates as of the day before the year began. This is what keeps "just this
 * year's amount" (the GL posting, unaffected by this) visibly reconciled
 * against the running total, and what makes a leaver's final year show
 * their true closing entitlement as of the date they actually left, not
 * 31/12.
 */
class EndOfServiceGratuityClosingVoucherService
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly EndOfServiceGratuityService $gratuity,
    ) {}

    /**
     * @param  list<array{account: string, detail: string|null, amount: float}>  $debits
     * @return array{
     *     period: string,
     *     debits: list<array{account: string, detail: string|null, amount: float}>,
     *     credits: list<array{account: string, detail: string|null, amount: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     balanced: bool,
     *     employee_count: int,
     * }
     */
    private function shape(string $period, array $debits, int $employeeCount): array
    {
        $totalDebit = round((float) array_sum(array_column($debits, 'amount')), 2);

        $credits = $totalDebit > 0
            ? [['account' => PayrollJournalVoucherService::GL_EOSG_PROVISION, 'detail' => null, 'amount' => $totalDebit]]
            : [];

        return [
            'period' => $period,
            'debits' => $debits,
            'credits' => $credits,
            'total_debit' => $totalDebit,
            'total_credit' => $totalDebit,
            'balanced' => true,
            'employee_count' => $employeeCount,
        ];
    }

    /**
     * The saved voucher for a year, or null if nobody has generated one yet.
     *
     * Reads only what `generate()` last wrote — it never recomputes from
     * payslips, so it stays exactly what was posted even if a payroll run in
     * that year is corrected afterwards. The journal entry (`debits`/`credits`)
     * carries only this year's movement, unchanged from before — `rollforward`
     * is the added, purely informational opening/movement/closing/paid
     * breakdown per employee.
     *
     * @return array{
     *     period: string,
     *     debits: list<array{account: string, detail: string|null, amount: float}>,
     *     credits: list<array{account: string, detail: string|null, amount: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     balanced: bool,
     *     employee_count: int,
     *     generated_at: string,
     *     rollforward: list<array{
     *         party_name: string,
     *         opening_balance: float,
     *         current_year_amount: float,
     *         closing_balance: float,
     *         left_during_year: bool,
     *         date_of_leaving: string|null,
     *         paid_amount: float,
     *         outstanding: float,
     *         payment_status: string,
     *     }>,
     * }|null
     */
    public function forYear(int $year): ?array
    {
        $voucher = EosgClosingVoucher::query()
            ->where('year', $year)
            ->with('lines.party.employeeProfile')
            ->first();

        if (! $voucher) {
            return null;
        }

        $debits = $voucher->lines
            ->map(fn (EosgClosingVoucherLine $line): array => [
                'account' => PayrollService::GL_EOSG_EXPENSE,
                'detail' => $line->party->name,
                'amount' => (float) $line->amount,
            ])
            ->sortBy('detail')
            ->values()
            ->all();

        $rollforward = $voucher->lines
            ->map(function (EosgClosingVoucherLine $line) use ($year): array {
                $profile = $line->party->employeeProfile;
                $leftOn = $profile?->getAttribute('date_of_leaving');
                $leftDuringYear = $leftOn !== null && $leftOn->year === $year;

                $closingBalance = (float) $line->closing_balance;
                $paidAmount = (float) ($profile?->getAttribute('eosg_paid_amount') ?? 0.0);
                $outstanding = round(max(0.0, $closingBalance - $paidAmount), 2);

                return [
                    'party_name' => $line->party->name,
                    'opening_balance' => (float) $line->opening_balance,
                    'current_year_amount' => (float) $line->amount,
                    'closing_balance' => $closingBalance,
                    'left_during_year' => $leftDuringYear,
                    'date_of_leaving' => $leftOn?->toDateString(),
                    'paid_amount' => $paidAmount,
                    'outstanding' => $outstanding,
                    'payment_status' => $this->paymentStatus($closingBalance, $paidAmount),
                ];
            })
            ->sortBy('party_name')
            ->values()
            ->all();

        return [
            ...$this->shape((string) __(':year — Annual Closing', ['year' => $year]), $debits, $voucher->lines->count()),
            'generated_at' => $voucher->generated_at->toIso8601String(),
            'rollforward' => $rollforward,
        ];
    }

    /**
     * Unpaid, partially paid, or paid in full — compared against the
     * cumulative closing balance, not just this year's movement, since
     * gratuity is settled against the whole entitlement, not a single year's
     * slice of it.
     */
    private function paymentStatus(float $closingBalance, float $paidAmount): string
    {
        if ($paidAmount <= 0.0) {
            return (string) __('Unpaid');
        }

        if ($paidAmount >= $closingBalance) {
            return (string) __('Paid in Full');
        }

        return (string) __('Partially Paid');
    }

    /**
     * Recompute a calendar year from the payslips and save it, replacing
     * whatever was saved for that year before.
     */
    public function generate(int $year): EosgClosingVoucher
    {
        $lines = PayslipLine::query()
            ->where('kind', PayslipLineKind::EMPLOYER_COST)
            ->whereHas('payslip.payrollRun', fn ($query) => $query
                ->whereBetween('period', ["{$year}-01", "{$year}-12"]))
            ->whereHas('payslip.party.employeeProfile', fn ($query) => $query
                ->where('is_eosg_applicable', true))
            ->with(['payslip.party.employeeProfile', 'payslip.payrollRun'])
            ->get();

        $byParty = $lines->groupBy(fn (PayslipLine $line): int => $line->payslip->party->getKey());

        $amountsByParty = $byParty
            ->filter(fn (Collection $partyLines, int $partyId): bool => $this->hasCompletePayslipCoverage(
                $partyLines->first()->payslip->party,
                $year,
                $partyLines,
            ))
            ->map(fn (Collection $partyLines): float => round((float) $partyLines->sum('amount'), 2))
            ->filter(fn (float $amount): bool => $amount > 0);

        foreach ($this->partiesWithoutPayslipsIn($year, $amountsByParty) as $party) {
            $amount = round($this->syntheticAnnualAccrual($party, $year), 2);

            if ($amount > 0) {
                $amountsByParty[$party->getKey()] = $amount;
            }
        }

        $amountsByParty = $this->applyOpeningBalances($year, $amountsByParty)
            ->filter(fn (float $amount): bool => $amount > 0);

        $totalAmount = round((float) $amountsByParty->sum(), 2);

        return DB::transaction(function () use ($year, $amountsByParty, $totalAmount): EosgClosingVoucher {
            $voucher = EosgClosingVoucher::updateOrCreate(
                ['year' => $year],
                ['total_amount' => $totalAmount, 'generated_at' => now()],
            );

            // Replaced wholesale rather than diffed — a year that shrinks to
            // fewer employees (an EOSG-applicable flag flipped off after the
            // fact, say) must not leave a stale line behind.
            $voucher->lines()->delete();

            $parties = Party::with('employeeProfile')
                ->whereIn('id', $amountsByParty->keys())
                ->get()
                ->keyBy(fn (Party $party): int => $party->getKey());

            $voucher->lines()->createMany(
                $amountsByParty->map(function (float $amount, int $partyId) use ($year, $parties): array {
                    $opening = $this->openingBalanceFor($parties->get($partyId), $year);

                    return [
                        'party_id' => $partyId,
                        'opening_balance' => $opening,
                        'amount' => $amount,
                        'closing_balance' => round($opening + $amount, 2),
                    ];
                })->values()->all(),
            );

            return $voucher;
        });
    }

    /**
     * An employee's cumulative liability at the START of a year — chained
     * from whichever year was generated immediately before this one for them
     * (its saved closing balance), so a correction to an earlier year and a
     * re-generation of this one stay in step. For whichever year is
     * generated FIRST for an employee, there is nothing to chain from, so
     * this is computed fresh from their service dates as of the day before
     * the year began.
     */
    private function openingBalanceFor(Party $party, int $year): float
    {
        $priorLine = EosgClosingVoucherLine::query()
            ->where('party_id', $party->getKey())
            ->whereHas('voucher', fn ($query) => $query->where('year', '<', $year))
            ->with('voucher')
            ->get()
            ->sortByDesc(fn (EosgClosingVoucherLine $line): int => $line->voucher->year)
            ->first();

        if ($priorLine !== null) {
            return (float) $priorLine->closing_balance;
        }

        $joinedOn = $party->employeeProfile?->getAttribute('date_of_joining');

        if ($joinedOn === null) {
            return 0.0;
        }

        $dayBeforeYear = Carbon::create($year, 1, 1)->startOfDay()->subDay();
        $basic = (float) ($this->payroll->salaryAt($party->getKey(), $dayBeforeYear)[SalaryComponent::BASIC->value] ?? 0.0);

        if ($basic <= 0) {
            return 0.0;
        }

        return $this->gratuity->gratuityAsOf($joinedOn, $dayBeforeYear, $basic);
    }

    /**
     * Adds each employee's one-time opening EOSG balance on top of whichever
     * year is generated first for them.
     *
     * @param  Collection<int, float>  $amountsByParty  Keyed by party id
     * @return Collection<int, float>
     */
    private function applyOpeningBalances(int $year, Collection $amountsByParty): Collection
    {
        $withOpeningBalance = Party::withRole('employee')
            ->with('employeeProfile')
            ->get()
            ->filter(fn (Party $party): bool => ($party->employeeProfile?->getAttribute('is_eosg_applicable') ?? true)
                && (float) ($party->employeeProfile?->getAttribute('opening_eosg_balance') ?? 0) > 0);

        if ($withOpeningBalance->isEmpty()) {
            return $amountsByParty;
        }

        // Excludes the year being generated — its own lines are about to be
        // wiped and recreated, so they can never count as "prior" history.
        $partiesAlreadyGranted = EosgClosingVoucherLine::query()
            ->whereIn('party_id', $withOpeningBalance->pluck('id'))
            ->whereHas('voucher', fn ($query) => $query->where('year', '!=', $year))
            ->pluck('party_id')
            ->all();

        foreach ($withOpeningBalance as $party) {
            if (in_array($party->getKey(), $partiesAlreadyGranted, true)) {
                continue;
            }

            $opening = (float) $party->employeeProfile->getAttribute('opening_eosg_balance');

            $amountsByParty[$party->getKey()] = round(($amountsByParty[$party->getKey()] ?? 0.0) + $opening, 2);
        }

        return $amountsByParty;
    }

    /**
     * Whether every month this employee was actually employed in during the
     * year has an EOSG payslip line — not merely whether some do.
     *
     * A partial year (payroll only started partway through, or a run was
     * later deleted) must not be summed as-is and passed off as the year's
     * figure; it is treated exactly like a year with no payslips at all, and
     * `syntheticAnnualAccrual()` recomputes the whole year from service dates
     * instead.
     *
     * @param  Collection<int, PayslipLine>  $partyLines
     */
    private function hasCompletePayslipCoverage(Party $party, int $year, Collection $partyLines): bool
    {
        $expected = $this->employedPeriodsIn($party, $year);

        if ($expected->isEmpty()) {
            return false;
        }

        $actual = $partyLines
            ->map(fn (PayslipLine $line): string => $line->payslip->payrollRun->getAttribute('period'))
            ->unique();

        return $expected->diff($actual)->isEmpty();
    }

    /**
     * The 'Y-m' periods this employee was on the payroll for within a year —
     * every calendar month from whichever is later of joining or the year's
     * start, to whichever is earlier of leaving or the year's end.
     *
     * @return Collection<int, string>
     */
    private function employedPeriodsIn(Party $party, int $year): Collection
    {
        $profile = $party->employeeProfile;
        $joinedOn = $profile?->getAttribute('date_of_joining');

        if ($joinedOn === null) {
            return collect();
        }

        $yearStart = Carbon::create($year, 1, 1)->startOfMonth();
        $yearEnd = Carbon::create($year, 12, 1)->startOfMonth();

        $leftOn = $profile->getAttribute('date_of_leaving');

        $start = $joinedOn->greaterThan($yearStart) ? $joinedOn->copy()->startOfMonth() : $yearStart;
        $end = ($leftOn !== null && $leftOn->lessThan(Carbon::create($year, 12, 31)))
            ? $leftOn->copy()->startOfMonth()
            : $yearEnd;

        if ($end->lessThan($start)) {
            return collect();
        }

        $periods = collect();

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            $periods->push($cursor->format('Y-m'));
        }

        return $periods;
    }

    /**
     * EOSG-applicable employees with no complete, trustworthy monthly figure
     * for this year — either no payslips at all, or an incomplete run of
     * them — so a synthetic figure is computed for them instead.
     *
     * @param  Collection<int, float>  $amountsByParty  Keyed by party id
     * @return Collection<int, Party>
     */
    private function partiesWithoutPayslipsIn(int $year, Collection $amountsByParty): Collection
    {
        return Party::withRole('employee')
            ->with('employeeProfile')
            ->get()
            ->filter(fn (Party $party): bool => ! $amountsByParty->has($party->getKey())
                && ($party->employeeProfile?->getAttribute('is_eosg_applicable') ?? true)
                && $party->employeeProfile?->getAttribute('date_of_joining') !== null);
    }

    /**
     * A whole year's gratuity accrual computed from service dates and the
     * basic salary on record at the year's end, for an employee this system
     * never ran a payslip for in that year.
     *
     * Mirrors `PayrollService::accrueGratuity()`'s use of
     * `EndOfServiceGratuityService::accrualBetween()`, just spanning the
     * calendar year instead of a month, since nothing more granular is
     * available.
     */
    private function syntheticAnnualAccrual(Party $party, int $year): float
    {
        $profile = $party->employeeProfile;
        $joinedOn = $profile?->getAttribute('date_of_joining');

        if ($joinedOn === null) {
            return 0.0;
        }

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $leftOn = $profile->getAttribute('date_of_leaving');
        $periodEnd = ($leftOn !== null && $leftOn->lessThan($yearEnd)) ? $leftOn : $yearEnd;

        // Not employed at any point during this year — left before it started,
        // or joined after it ended.
        if ($periodEnd->lessThan($yearStart) || $joinedOn->greaterThan($periodEnd)) {
            return 0.0;
        }

        $basic = (float) ($this->payroll->salaryAt($party->getKey(), $periodEnd)[SalaryComponent::BASIC->value] ?? 0.0);

        if ($basic <= 0) {
            return 0.0;
        }

        return max(0.0, $this->gratuity->accrualBetween($joinedOn, $yearStart, $periodEnd, $basic));
    }
}
