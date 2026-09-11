<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\PayrollRunStatus;
use App\Models\EmployeeLoan;
use App\Models\EosgAccrual;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Movement up and down the payroll approval ladder.
 *
 * The transitions live here rather than in the page actions so the rules hold
 * however the run is moved — a button, a command, a future API. Each rung
 * records WHO asserted what: "HR checked the days" and "Finance released the
 * money" are different claims by different people, and collapsing them into one
 * Approved flag would lose the only thing the ladder exists to capture.
 */
class PayrollRunService
{
    /**
     * Send a generated draft to HR.
     */
    public function submitForReview(PayrollRun $run): PayrollRun
    {
        $this->assertStatus($run, PayrollRunStatus::DRAFT);

        if ($run->payslips()->count() === 0) {
            throw new RuntimeException('Generate the payslips before sending the run for review.');
        }

        return $this->moveTo($run, PayrollRunStatus::HR_REVIEW);
    }

    /**
     * HR signs off on the days and passes the run to Finance.
     */
    public function hrApprove(PayrollRun $run, User $approver): PayrollRun
    {
        $this->assertStatus($run, PayrollRunStatus::HR_REVIEW);

        // A negative net is HR's to resolve — the deductions are wrong, or the
        // loan is being recovered too fast. Passing it to Finance would send a
        // bank file asking for money back from the employee.
        if ($run->payslips()->where('needs_review', true)->exists()) {
            throw new RuntimeException('Some payslips are flagged for review. Resolve them before approving.');
        }

        return $this->moveTo($run, PayrollRunStatus::FINANCE_APPROVAL, [
            'hr_approved_by' => $approver->getKey(),
            'hr_approved_at' => now(),
        ]);
    }

    /**
     * Finance releases the money.
     */
    public function financeApprove(PayrollRun $run, User $approver): PayrollRun
    {
        $this->assertStatus($run, PayrollRunStatus::FINANCE_APPROVAL);

        return $this->moveTo($run, PayrollRunStatus::APPROVED, [
            'finance_approved_by' => $approver->getKey(),
            'finance_approved_at' => now(),
        ]);
    }

    /**
     * Record that the transfers went out.
     *
     * This is the point at which the month becomes history: gratuity accruals are
     * booked and any loan whose last instalment has now been taken is closed.
     */
    public function disburse(PayrollRun $run): PayrollRun
    {
        $this->assertStatus($run, PayrollRunStatus::APPROVED);

        return DB::transaction(function () use ($run): PayrollRun {
            $this->bookGratuityAccruals($run);
            $this->settleFullyRecoveredLoans($run);

            return $this->moveTo($run, PayrollRunStatus::DISBURSED, ['disbursed_at' => now()]);
        });
    }

    /**
     * Send a run back to draft so its figures can be corrected.
     *
     * The approvals are cleared, not kept. An approval belongs to the numbers it
     * was given; carrying it across a recalculation would show Finance's name
     * against figures they never saw.
     */
    public function returnToDraft(PayrollRun $run): PayrollRun
    {
        if ($run->getAttribute('status') === PayrollRunStatus::DISBURSED) {
            throw new RuntimeException('A disbursed run cannot be reopened.');
        }

        return $this->moveTo($run, PayrollRunStatus::DRAFT, [
            'hr_approved_by' => null,
            'hr_approved_at' => null,
            'finance_approved_by' => null,
            'finance_approved_at' => null,
        ]);
    }

    /**
     * Write one gratuity accrual row per employee for the period.
     */
    private function bookGratuityAccruals(PayrollRun $run): void
    {
        $gratuity = app(EndOfServiceGratuityService::class);
        [, $periodEnd] = $run->dateRange();

        $payslips = Payslip::query()
            ->where('payroll_run_id', $run->getKey())
            ->with('party.employeeProfile')
            ->get();

        foreach ($payslips as $payslip) {
            $joinedOn = $payslip->party->employeeProfile?->getAttribute('date_of_joining');

            if ($joinedOn === null) {
                continue;
            }

            $unpaidDays = (float) $payslip->getAttribute('unpaid_days');

            $serviceDays = $gratuity->serviceDays($joinedOn, $periodEnd, $unpaidDays);

            $basic = (float) $payslip->getAttribute('basic_snapshot');

            EosgAccrual::updateOrCreate(
                [
                    'party_id' => $payslip->getAttribute('party_id'),
                    'period' => $run->getAttribute('period'),
                ],
                [
                    'service_days' => $serviceDays,
                    'basic_snapshot' => $basic,
                    'accrued_this_month' => (float) $payslip->getAttribute('eosg_accrued'),
                    // What settlement would cost today, cap already applied.
                    'cumulative_liability' => $gratuity->gratuityAsOf($joinedOn, $periodEnd, $basic, $unpaidDays),
                ],
            );
        }
    }

    /**
     * Close any loan whose instalments have all been taken.
     */
    private function settleFullyRecoveredLoans(PayrollRun $run): void
    {
        $partyIds = $run->payslips()->pluck('party_id');

        EmployeeLoan::query()
            ->whereIn('party_id', $partyIds)
            ->where('status', LoanStatus::ACTIVE->value)
            ->whereDoesntHave('installments', fn ($query) => $query->whereNull('payslip_id'))
            ->get()
            ->each(fn (EmployeeLoan $loan) => $loan->forceFill(['status' => LoanStatus::SETTLED])->save());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function moveTo(PayrollRun $run, PayrollRunStatus $status, array $attributes = []): PayrollRun
    {
        $run->forceFill(['status' => $status, ...$attributes])->save();

        return $run;
    }

    private function assertStatus(PayrollRun $run, PayrollRunStatus $expected): void
    {
        if ($run->getAttribute('status') !== $expected) {
            throw new RuntimeException(sprintf(
                'This run is %s, not %s.',
                $run->getAttribute('status')->value,
                $expected->value,
            ));
        }
    }

    /**
     * Totals for a run, for the header and the voucher.
     *
     * @return array{employees: int, gross: float, deductions: float, net: float, eosg: float}
     */
    public function totals(PayrollRun $run): array
    {
        /** @var object{employees: int, gross: float|null, deductions: float|null, net: float|null, eosg: float|null} $row */
        $row = Payslip::query()
            ->where('payroll_run_id', $run->getKey())
            ->selectRaw('count(*) as employees')
            ->selectRaw('sum(gross) as gross')
            ->selectRaw('sum(total_deductions) as deductions')
            ->selectRaw('sum(net_pay) as net')
            ->selectRaw('sum(eosg_accrued) as eosg')
            ->first();

        return [
            'employees' => (int) $row->employees,
            'gross' => (float) ($row->gross ?? 0),
            'deductions' => (float) ($row->deductions ?? 0),
            'net' => (float) ($row->net ?? 0),
            'eosg' => (float) ($row->eosg ?? 0),
        ];
    }
}
