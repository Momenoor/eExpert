<?php

namespace App\Services;

use App\Enums\PayslipLineKind;
use App\Models\PayslipLine;
use Illuminate\Support\Collection;

/**
 * The annual EOSG closing journal voucher, posted once a year at 31/12.
 *
 * Every payroll run still computes and stores that month's gratuity accrual on
 * its payslips (`PayslipLine::EMPLOYER_COST`, via `PayrollService::accrueGratuity()`)
 * — the monthly figure stays accurate under mid-year salary changes because it is
 * a telescoping difference of cumulative entitlement. What changes is only when
 * that accrual reaches the general ledger: this service sums a calendar year's
 * worth of those stored lines into one closing entry, rather than the monthly
 * Salaries voucher (`PayrollJournalVoucherService`) posting a slice of it every
 * month.
 *
 *   Dr  Employer EOSG accrual expense   — itemised per employee
 *     Cr  EOSG provision (liability)    — the year's total, in one line
 *
 * An employee whose profile has `is_eosg_applicable` set to false never accrues
 * anything in the first place (`PayrollService::accrueGratuity()` returns 0 for
 * them), so excluding them again here is a defensive second check rather than
 * the only one.
 */
class EndOfServiceGratuityClosingVoucherService
{
    /**
     * Build the closing voucher for a calendar year.
     *
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
    public function forYear(int $year): array
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

        $debits = $this->itemisedByParty($byParty);

        $totalDebit = round((float) array_sum(array_column($debits, 'amount')), 2);

        $credits = $totalDebit > 0
            ? [['account' => PayrollJournalVoucherService::GL_EOSG_PROVISION, 'detail' => null, 'amount' => $totalDebit]]
            : [];

        return [
            'period' => (string) __(':year — Annual Closing', ['year' => $year]),
            'debits' => $debits,
            'credits' => $credits,
            'total_debit' => $totalDebit,
            'total_credit' => $totalDebit,
            'balanced' => true,
            'employee_count' => $byParty->count(),
        ];
    }

    /**
     * @param  Collection<int, Collection<int, PayslipLine>>  $byParty
     * @return list<array{account: string, detail: string|null, amount: float}>
     */
    private function itemisedByParty(Collection $byParty): array
    {
        $rows = $byParty->map(fn (Collection $lines): array => [
            'account' => PayrollService::GL_EOSG_EXPENSE,
            'detail' => $lines->first()->payslip->party->name,
            'amount' => round((float) $lines->sum('amount'), 2),
        ])
            ->filter(fn (array $row): bool => $row['amount'] > 0)
            ->values();

        return $rows
            ->sortBy('detail')
            ->values()
            ->all();
    }
}
