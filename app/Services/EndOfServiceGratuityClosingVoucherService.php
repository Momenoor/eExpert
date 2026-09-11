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
 * A year with no payslips at all for a given employee — typically because the
 * office had not yet started running payroll through this system, or an
 * employee's records were entered after the fact — is not simply skipped.
 * `generate()` falls back to a synthetic annual figure for exactly that
 * employee in exactly that year, computed the same way as a month's accrual
 * (`gratuityFor(closing days) - gratuityFor(opening days)`, see
 * `EndOfServiceGratuityService::monthlyAccrual()`) but spanning the whole
 * calendar year, using whichever basic salary was on record at the year's end
 * (`PayrollService::salaryAt()`). Nothing here assumes the salary was constant
 * for the whole year — only that the year-end figure is the best available
 * estimate when no monthly record exists to do better.
 *
 * An employee's `opening_eosg_balance` — gratuity entered once by HR/Finance
 * for service this system has no record of at all — is added on top of
 * whichever voucher is generated FIRST for that employee (checked by whether
 * any `EosgClosingVoucherLine` already exists for them in a different year),
 * never again after that. Which year counts as "first" is whichever is
 * generated first in practice, not necessarily the chronologically earliest —
 * the same one-time-top-up rule `LeaveEntitlementService` applies to an
 * opening leave balance.
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
     * that year is corrected afterwards.
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
     * }|null
     */
    public function forYear(int $year): ?array
    {
        $voucher = EosgClosingVoucher::query()
            ->where('year', $year)
            ->with('lines.party')
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

        return [
            ...$this->shape((string) __(':year — Annual Closing', ['year' => $year]), $debits, $voucher->lines->count()),
            'generated_at' => $voucher->generated_at->toIso8601String(),
        ];
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
            ->with('payslip.party')
            ->get();

        $byParty = $lines->groupBy(fn (PayslipLine $line): int => $line->payslip->party->getKey());

        $amountsByParty = $byParty
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

            $voucher->lines()->createMany(
                $amountsByParty->map(fn (float $amount, int $partyId): array => [
                    'party_id' => $partyId,
                    'amount' => $amount,
                ])->values()->all(),
            );

            return $voucher;
        });
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
     * EOSG-applicable employees who accrued nothing from payslips in this
     * year — either because they were never run through payroll this system
     * knows about, or because that data was never entered.
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
     * Mirrors `PayrollService::accrueGratuity()`'s telescoping-difference
     * approach (`gratuityFor(closing) - gratuityFor(opening)`), just spanning
     * the calendar year instead of a month, since nothing more granular is
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

        $openingDays = $this->gratuity->serviceDays($joinedOn, $yearStart);
        $closingDays = $this->gratuity->serviceDays($joinedOn, $periodEnd);

        return max(0.0, $this->gratuity->monthlyAccrual($openingDays, $closingDays, $basic));
    }
}
