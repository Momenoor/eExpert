<?php

namespace App\Services;

use App\Enums\LoanKind;
use App\Enums\PayslipLineKind;
use App\Models\PayrollRun;
use App\Models\PayslipLine;

/**
 * The journal voucher a payroll run posts to QuickBooks.
 *
 * There is no QuickBooks integration here and this does not pretend otherwise:
 * the office keys the entry in by hand, so what this produces is a balanced,
 * account-by-account sheet to copy from — not an API payload.
 *
 * The double entry it describes:
 *
 *   Dr  Basic salary, allowances, incentives   — the full cost of the month
 *   Dr  Employer EOSG accrual                  — gratuity earned but unpaid
 *     Cr  Loan and petty cash clearing         — advances recovered, per employee
 *     Cr  Unpaid leave recovery                — pay withheld, contra to expense
 *     Cr  Other salary deductions              — fines and manual adjustments
 *     Cr  EOSG provision                       — the liability side of the accrual
 *     Cr  Net salary payable                   — what the bank transfer settles
 *
 * Earnings are debited GROSS and the withholdings credited back, rather than
 * debiting the net figure. That is what makes the sheet reconcilable: the salary
 * expense line matches the payroll register, and every deduction can be traced
 * to the account it landed in.
 */
class PayrollJournalVoucherService
{
    public const GL_EOSG_PROVISION = 'EOSG Provision (Liability)';

    public const GL_NET_SALARY_PAYABLE = 'Net Salary Payable';

    /**
     * Build the voucher for a run.
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
    public function forRun(PayrollRun $run): array
    {
        $lines = PayslipLine::query()
            ->whereIn('payslip_id', $run->payslips()->select('id'))
            ->with('payslip.party')
            ->get();

        $loanAccounts = array_map(
            fn (LoanKind $kind): string => $kind->glAccount(),
            LoanKind::cases(),
        );

        $debits = [];
        $credits = [];

        // Loan clearing is itemised by employee; everything else is summarised
        // by account. A single "Staff Loans Receivable" credit balances just as
        // well, but nobody can then tell whose advance it settled — and the loan
        // ledger is per person, so reconciling it against one lump sum means
        // recomputing the split by hand every month.
        $grouped = $lines->groupBy(fn (PayslipLine $line): string => in_array($line->gl_account, $loanAccounts, true)
            ? $line->gl_account."\0".$line->payslip->party->name
            : (string) $line->gl_account);

        foreach ($grouped as $group) {
            $amount = round((float) $group->sum('amount'), 2);

            if ($amount <= 0) {
                continue;
            }

            $first = $group->first();
            $account = (string) $first->gl_account;
            $isLoan = in_array($account, $loanAccounts, true);
            $detail = $isLoan ? $first->payslip->party->name : null;

            match ($first->kind) {
                PayslipLineKind::EARNING => $debits[] = ['account' => $account, 'detail' => $detail, 'amount' => $amount],
                PayslipLineKind::DEDUCTION => $credits[] = ['account' => $account, 'detail' => $detail, 'amount' => $amount],
                // An employer cost is the only line that posts to both sides:
                // it debits an expense and credits the provision it funds,
                // without ever passing through the employee's net pay.
                PayslipLineKind::EMPLOYER_COST => [
                    $debits[] = ['account' => $account, 'detail' => null, 'amount' => $amount],
                    $credits[] = ['account' => self::GL_EOSG_PROVISION, 'detail' => null, 'amount' => $amount],
                ],
            };
        }

        $netPay = round((float) $run->payslips()->sum('net_pay'), 2);

        if ($netPay > 0) {
            $credits[] = ['account' => self::GL_NET_SALARY_PAYABLE, 'detail' => null, 'amount' => $netPay];
        }

        $debits = $this->merged($debits);
        $credits = $this->merged($credits);

        $totalDebit = round(array_sum(array_column($debits, 'amount')), 2);
        $totalCredit = round(array_sum(array_column($credits, 'amount')), 2);

        return [
            'period' => (string) $run->getAttribute('period'),
            'debits' => $debits,
            'credits' => $credits,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            // Compared with a half-fils tolerance rather than for exact equality:
            // both sides are sums of independently rounded figures, and refusing
            // to call a 0.001 difference balanced would fail every large run.
            'balanced' => abs($totalDebit - $totalCredit) < 0.005,
            'employee_count' => $run->payslips()->count(),
        ];
    }

    /**
     * Collapse repeated rows into one each, largest first.
     *
     * Keyed on the account AND its detail, so two employees repaying against the
     * same loan account stay on separate lines while everything else still
     * consolidates.
     *
     * @param  list<array{account: string, detail: string|null, amount: float}>  $entries
     * @return list<array{account: string, detail: string|null, amount: float}>
     */
    private function merged(array $entries): array
    {
        $totals = [];

        foreach ($entries as $entry) {
            $key = $entry['account']."\0".($entry['detail'] ?? '');

            $totals[$key] = [
                'account' => $entry['account'],
                'detail' => $entry['detail'],
                'amount' => round(($totals[$key]['amount'] ?? 0) + $entry['amount'], 2),
            ];
        }

        // Ordered by account first so an employee-itemised block stays together,
        // then by size within it.
        uasort($totals, function (array $a, array $b): int {
            return [$a['account'], -$a['amount']] <=> [$b['account'], -$b['amount']];
        });

        return array_values($totals);
    }
}
