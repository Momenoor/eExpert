<?php

namespace App\Services;

use App\Enums\PayslipLineKind;
use App\Enums\SalaryComponent;
use App\Models\EmployeeSalaryComponent;
use App\Models\IncentiveCalculation;
use App\Models\LoanInstallment;
use App\Models\Party;
use App\Models\PartyLeave;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds a month's payslips.
 *
 * Every figure is written onto the payslip rather than left to be derived on
 * read. A payslip is a statement about a month that has closed: if next April's
 * salary revision or a corrected leave record could change what March's payslip
 * displays, the document is worthless as a record, and the office would have no
 * way to reconcile a bank transfer against it.
 */
class PayrollService
{
    public const GL_INCENTIVE = 'Staff Incentive Expense';

    public const GL_UNPAID_LEAVE = 'Unpaid Leave Recovery';

    public const GL_MANUAL_DEDUCTION = 'Other Salary Deductions';

    public const GL_EOSG_EXPENSE = 'Employer EOSG Accrual Expense';

    public function __construct(
        private readonly EndOfServiceGratuityService $gratuity,
    ) {}

    /**
     * Regenerate every payslip in a run.
     *
     * Only a draft run may be generated. Once HR has reviewed the figures, the
     * numbers they signed off on must not move underneath them; a correction
     * after that point means sending the run back to draft, which is a decision
     * with a name on it rather than a side effect of pressing Generate.
     *
     * @return Collection<int, Payslip>
     */
    public function generate(PayrollRun $run): Collection
    {
        if (! $run->isEditable()) {
            throw new RuntimeException('Only a draft payroll run can be generated.');
        }

        [$periodStart, $periodEnd] = $run->dateRange();

        return DB::transaction(function () use ($run, $periodStart, $periodEnd): Collection {
            // Hand-entered figures are the one thing generation must not
            // discard: an accountant who typed a 500 fine into the draft would
            // otherwise lose it every time anyone pressed Generate.
            $manual = $run->payslips()->pluck('manual_deduction', 'party_id');

            // The incentive is derived, so it is re-imported — except where
            // somebody deliberately corrected it, which the flag records.
            $overridden = $run->payslips()->where('incentive_overridden', true)
                ->pluck('incentive_amount', 'party_id');

            $imported = $this->incentivesForPeriod($run);

            $this->clear($run);

            return Party::withRole('employee')
                ->with('employeeProfile')
                ->get()
                ->filter(fn (Party $party): bool => $this->isOnPayroll($party, $periodEnd))
                ->map(fn (Party $party): Payslip => $this->buildPayslip(
                    $run,
                    $party,
                    $periodStart,
                    $periodEnd,
                    (float) ($manual[$party->getKey()] ?? 0),
                    (float) ($overridden[$party->getKey()] ?? $imported[$party->getKey()] ?? 0),
                    $overridden->has($party->getKey()),
                ))
                ->values();
        });
    }

    /**
     * Incentive payable to each employee for a run's period, keyed by party id.
     *
     * A calculation belongs to the payroll month its period ENDS in: a cycle
     * running 26 June to 25 August is August's incentive, because that is when
     * it was closed and when the office pays it. Keying off the start date would
     * put it in June, two months before anyone could know the figure.
     *
     * Only FINALISED calculations are imported. A draft is recalculated every
     * time somebody presses a button, and a payslip is a snapshot Finance signs;
     * basing one on figures that move underneath it is exactly what the approval
     * ladder exists to prevent. Drafts ending in the period are reported by
     * pendingIncentiveCalculations() instead, so their absence is explained
     * rather than silent.
     *
     * @return Collection<int, float>
     */
    public function incentivesForPeriod(PayrollRun $run): Collection
    {
        $calculator = app(IncentiveCalculatorService::class);

        return $this->calculationsEndingIn($run)
            ->filter(fn (IncentiveCalculation $calculation): bool => $calculation->isFinalized())
            ->reduce(function (Collection $carry, IncentiveCalculation $calculation) use ($calculator): Collection {
                // Two calculations closing in one month — a cycle plus a
                // correction run — are both owed, so they add rather than
                // replace one another.
                foreach ($calculator->payableByParty($calculation) as $partyId => $amount) {
                    $carry[$partyId] = round(($carry[$partyId] ?? 0) + $amount, 2);
                }

                return $carry;
            }, collect());
    }

    /**
     * Calculations that close in this period but are not finalised yet.
     *
     * These contribute nothing to the payslips, and the run is worth flagging
     * for it: an incentive month that ends inside the payroll month and pays
     * nobody is far more likely to be an unfinalised draft than a month in which
     * nobody earned anything.
     *
     * @return Collection<int, IncentiveCalculation>
     */
    public function pendingIncentiveCalculations(PayrollRun $run): Collection
    {
        return $this->calculationsEndingIn($run)
            ->reject(fn (IncentiveCalculation $calculation): bool => $calculation->isFinalized())
            ->values();
    }

    /**
     * @return Collection<int, IncentiveCalculation>
     */
    private function calculationsEndingIn(PayrollRun $run): Collection
    {
        [$periodStart, $periodEnd] = $run->dateRange();

        return IncentiveCalculation::query()
            ->whereDate('period_end', '>=', $periodStart->toDateString())
            ->whereDate('period_end', '<=', $periodEnd->toDateString())
            ->get();
    }

    /**
     * Employee-role parties a run will pass over, and why.
     *
     * Generation silently skips anyone it cannot price. Silence is the wrong
     * answer here: marking ten parties as employees and getting three payslips
     * looks exactly like a working run until somebody is not paid, so the
     * omissions are reported back rather than swallowed.
     *
     * @return Collection<int, array{party: Party, reason: string}>
     */
    public function skippedEmployees(PayrollRun $run): Collection
    {
        [$periodStart, $periodEnd] = $run->dateRange();

        return Party::withRole('employee')
            ->with('employeeProfile')
            ->get()
            ->map(function (Party $party) use ($periodEnd): ?array {
                $profile = $party->employeeProfile;

                if ($profile === null) {
                    return ['party' => $party, 'reason' => (string) __('No employee record')];
                }

                if (! $this->isOnPayroll($party, $periodEnd)) {
                    return ['party' => $party, 'reason' => (string) __('Not employed during this period')];
                }

                if (($this->salaryAt($party->getKey(), $periodEnd)[SalaryComponent::BASIC->value] ?? 0) <= 0) {
                    // Priced at nothing. The payslip would generate, but at zero,
                    // which is worth saying out loud before anyone approves it.
                    return ['party' => $party, 'reason' => (string) __('No basic salary in force')];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * Discard a draft run's payslips and release the instalments they held.
     */
    public function clear(PayrollRun $run): void
    {
        $payslipIds = $run->payslips()->pluck('id');

        if ($payslipIds->isEmpty()) {
            return;
        }

        // Release first. An instalment still pointing at a deleted payslip would
        // read as already recovered and never be deducted again — the loan would
        // quietly write itself off.
        LoanInstallment::whereIn('payslip_id', $payslipIds)->update(['payslip_id' => null]);

        PayslipLine::whereIn('payslip_id', $payslipIds)->delete();
        $run->payslips()->delete();
    }

    /**
     * Days of pay withheld for leave falling inside a period.
     *
     * Counted from the leave ledger, so hand-entered absences count exactly as
     * approved requests do.
     */
    public function unpaidDaysInPeriod(int $partyId, CarbonInterface $start, CarbonInterface $end): float
    {
        $leaves = PartyLeave::query()
            ->where('party_id', $partyId)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with('leaveRequestPeriod')
            ->get();

        $unpaid = 0.0;

        foreach ($leaves as $leave) {
            $factor = $leave->payFactor();

            if ($factor >= 1.0) {
                continue;
            }

            $unpaid += $this->daysInWindow($leave, $start, $end) * (1.0 - $factor);
        }

        return round($unpaid, 1);
    }

    /**
     * Salary components in force at a date, keyed by component.
     *
     * @return Collection<string, float>
     */
    public function salaryAt(int $partyId, CarbonInterface $on): Collection
    {
        return EmployeeSalaryComponent::query()
            ->where('party_id', $partyId)
            ->effectiveOn($on->toDateString())
            ->get()
            ->mapWithKeys(fn (EmployeeSalaryComponent $component): array => [
                $component->getAttribute('component')->value => (float) $component->getAttribute('amount'),
            ]);
    }

    private function buildPayslip(
        PayrollRun $run,
        Party $party,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        float $manualDeduction,
        float $incentive,
        bool $incentiveOverridden = false,
    ): Payslip {
        $salary = $this->salaryAt($party->getKey(), $periodEnd);

        $basic = $salary[SalaryComponent::BASIC->value] ?? 0.0;
        $allowances = (float) $salary->except(SalaryComponent::BASIC->value)->sum();

        $unpaidDays = $this->unpaidDaysInPeriod($party->getKey(), $periodStart, $periodEnd);
        // Shared with the gratuity service's own daily rate, under the same
        // setting key — basic salary ÷ a month has to mean the same thing on
        // both figures, or the two would silently disagree about the divisor.
        $unpaidDeduction = round(($basic / $this->gratuity->daysPerMonth()) * $unpaidDays, 2);

        $installments = $this->dueInstallments($party, $run->getAttribute('period'));
        $loanDeduction = round((float) $installments->sum('amount'), 2);

        $gross = round($basic + $allowances + $incentive, 2);
        $totalDeductions = round($unpaidDeduction + $loanDeduction + $manualDeduction, 2);
        $net = round($gross - $totalDeductions, 2);

        $profile = $party->employeeProfile;

        $payslip = Payslip::create([
            'payroll_run_id' => $run->getKey(),
            'party_id' => $party->getKey(),
            'basic_snapshot' => $basic,
            'allowances_snapshot' => $allowances,
            'incentive_amount' => $incentive,
            'incentive_overridden' => $incentiveOverridden,
            'gross' => $gross,
            'unpaid_days' => $unpaidDays,
            'unpaid_deduction' => $unpaidDeduction,
            'loan_deduction' => $loanDeduction,
            'manual_deduction' => $manualDeduction,
            'total_deductions' => $totalDeductions,
            'net_pay' => $net,
            'eosg_accrued' => $this->accrueGratuity($party, $periodStart, $periodEnd, $basic, $unpaidDays),
            'iban_snapshot' => $profile?->getAttribute('iban'),
            'bank_name_snapshot' => $profile?->getAttribute('bank_name'),
            // Surfaced, not clamped. A negative net means the deductions are
            // wrong or the loan schedule is too aggressive, and zeroing it would
            // hide the balance instead of paying it.
            'needs_review' => $net < 0,
            'review_note' => $net < 0 ? __('Deductions exceed gross pay for this period.') : null,
        ]);

        $installments->each(fn (LoanInstallment $installment) => $installment
            ->forceFill(['payslip_id' => $payslip->getKey()])->save());

        $this->writeLines($payslip, $salary, $incentive, $unpaidDeduction, $installments, $manualDeduction);

        return $payslip;
    }

    /**
     * @param  Collection<string, float>  $salary
     * @param  Collection<int, LoanInstallment>  $installments
     */
    private function writeLines(
        Payslip $payslip,
        Collection $salary,
        float $incentive,
        float $unpaidDeduction,
        Collection $installments,
        float $manualDeduction,
    ): void {
        $lines = [];

        foreach ($salary as $component => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $case = SalaryComponent::from($component);

            $lines[] = [
                'kind' => PayslipLineKind::EARNING,
                'label' => $case->getLabel(),
                'amount' => $amount,
                'gl_account' => $case->glAccount(),
            ];
        }

        if ($incentive > 0) {
            $lines[] = [
                'kind' => PayslipLineKind::EARNING,
                'label' => __('Incentive'),
                'amount' => $incentive,
                'gl_account' => self::GL_INCENTIVE,
            ];
        }

        if ($unpaidDeduction > 0) {
            $lines[] = [
                'kind' => PayslipLineKind::DEDUCTION,
                'label' => __('Unpaid Leave'),
                'amount' => $unpaidDeduction,
                'gl_account' => self::GL_UNPAID_LEAVE,
            ];
        }

        foreach ($installments as $installment) {
            $kind = $installment->loan->getAttribute('kind');

            $lines[] = [
                'kind' => PayslipLineKind::DEDUCTION,
                'label' => $kind->getLabel(),
                'amount' => (float) $installment->getAttribute('amount'),
                'gl_account' => $kind->glAccount(),
            ];
        }

        if ($manualDeduction > 0) {
            $lines[] = [
                'kind' => PayslipLineKind::DEDUCTION,
                'label' => __('Other Deduction'),
                'amount' => $manualDeduction,
                'gl_account' => self::GL_MANUAL_DEDUCTION,
            ];
        }

        $eosg = (float) $payslip->getAttribute('eosg_accrued');

        if ($eosg > 0) {
            $lines[] = [
                'kind' => PayslipLineKind::EMPLOYER_COST,
                'label' => __('End of Service Gratuity'),
                'amount' => $eosg,
                'gl_account' => self::GL_EOSG_EXPENSE,
            ];
        }

        foreach ($lines as $line) {
            PayslipLine::create(['payslip_id' => $payslip->getKey(), ...$line]);
        }
    }

    /**
     * Instalments falling due by this period that nothing has yet recovered.
     *
     * Earlier unrecovered instalments are swept in as well as the current one:
     * an employee who was skipped in March because they had no payslip still
     * owes March's instalment in April, and leaving it behind would silently
     * shorten the recovery.
     *
     * @return Collection<int, LoanInstallment>
     */
    private function dueInstallments(Party $party, string $period): Collection
    {
        return LoanInstallment::query()
            ->whereNull('payslip_id')
            ->where('due_period', '<=', $period)
            ->whereHas('loan', fn ($query) => $query
                ->where('party_id', $party->getKey())
                ->where('status', 'active'))
            ->with('loan')
            ->orderBy('due_period')
            ->get();
    }

    private function accrueGratuity(
        Party $party,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        float $basic,
        float $unpaidDays,
    ): float {
        $profile = $party->employeeProfile;
        $joinedOn = $profile?->getAttribute('date_of_joining');

        if ($joinedOn === null || $basic <= 0 || $profile?->getAttribute('is_eosg_applicable') === false) {
            return 0.0;
        }

        $openingDays = $this->gratuity->serviceDays($joinedOn, $periodStart);
        $closingDays = $this->gratuity->serviceDays($joinedOn, $periodEnd, $unpaidDays);

        return max(0.0, $this->gratuity->monthlyAccrual($openingDays, $closingDays, $basic));
    }

    private function isOnPayroll(Party $party, CarbonInterface $periodEnd): bool
    {
        $profile = $party->employeeProfile;

        if ($profile === null) {
            return false;
        }

        $joined = $profile->getAttribute('date_of_joining');
        $left = $profile->getAttribute('date_of_leaving');

        if ($joined !== null && $joined->greaterThan($periodEnd)) {
            return false;
        }

        // Someone who left mid-month is still paid for the days they worked, so
        // the test is against the start of the period, not the end.
        return $left === null || $left->greaterThanOrEqualTo($periodEnd->copy()->startOfMonth());
    }

    /**
     * Days of a leave row falling inside a window.
     */
    private function daysInWindow(PartyLeave $leave, CarbonInterface $start, CarbonInterface $end): float
    {
        $leaveStart = $leave->getAttribute('start_date');
        $leaveEnd = $leave->getAttribute('end_date');

        // A half-day is recorded as a one-day range with a day_count of 0.5.
        // Where the whole period sits inside the window, the recorded count is
        // the truthful one; only a period straddling the month edge has to be
        // measured off the calendar.
        $period = $leave->leaveRequestPeriod;

        if ($period !== null
            && $leaveStart->greaterThanOrEqualTo($start)
            && $leaveEnd->lessThanOrEqualTo($end)) {
            return (float) $period->getAttribute('day_count');
        }

        $from = $leaveStart->greaterThan($start) ? $leaveStart : $start;
        $to = $leaveEnd->lessThan($end) ? $leaveEnd : $end;

        // Both ends are flattened to midnight before the subtraction. The period
        // end arrives as 23:59:59, and diffing against that returns 2.999 days
        // for a three-day window — which then rounds up to four and charges an
        // extra day of unpaid leave at every month boundary.
        return (float) ($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
    }
}
