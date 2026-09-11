<?php

namespace App\Services;

use App\Enums\LoanKind;
use App\Enums\PayslipLineKind;
use App\Models\PayrollRun;
use App\Models\PayslipLine;
use App\Models\Setting;

/**
 * The monthly Salaries journal voucher a payroll run posts to QuickBooks.
 *
 * There is no QuickBooks integration here and this does not pretend otherwise:
 * the office keys the entry in by hand, so what this produces is a balanced,
 * account-by-account sheet to copy from — not an API payload.
 *
 * The double entry it describes:
 *
 *   Dr  Basic salary, allowances, incentives   — the full cost of the month
 *   Dr  Bank fees                              — the fixed monthly transfer charge
 *     Cr  Loan and petty cash clearing         — advances recovered, per employee
 *     Cr  Unpaid leave recovery                — pay withheld, contra to expense
 *     Cr  Other salary deductions              — fines and manual adjustments
 *     Cr  Net salary payable                   — what the bank transfer settles,
 *                                                 the bank fee folded in
 *
 * Earnings are debited GROSS and the withholdings credited back, rather than
 * debiting the net figure. That is what makes the sheet reconcilable: the salary
 * expense line matches the payroll register, and every deduction can be traced
 * to the account it landed in.
 *
 * The bank fee is a fixed office-wide charge from Payroll Settings, not derived
 * from any payslip — it posts once per run regardless of headcount, because the
 * bank charges the same transfer fee whether the batch holds five salaries or
 * fifty. It debits its own expense account so the cost stays visible on the
 * expense side, but credits straight into Net Salary Payable rather than a
 * separate payable account — the office settles both in the one bank transfer,
 * so the liability side is one figure, not two.
 *
 * End-of-service gratuity is deliberately absent from this voucher. It is still
 * accrued and stored on every payslip (`PayslipLine::EMPLOYER_COST`, via
 * `PayrollService::accrueGratuity()`), but it is posted only once a year, as its
 * own closing entry, by `EndOfServiceGratuityClosingVoucherService` — mixing a
 * once-a-year provision into twelve monthly vouchers made it harder, not easier,
 * to reconcile either one.
 */
class PayrollJournalVoucherService
{
    public const GL_EOSG_PROVISION = 'EOSG Provision (Liability)';

    public const GL_NET_SALARY_PAYABLE = 'Net Salary Payable';

    public const GL_BANK_FEES_EXPENSE = 'Bank Fees Expense';

    private const DEFAULT_BANK_FEE_AMOUNT = 0.0;

    /**
     * The fixed transfer fee from Payroll Settings — office policy, not a
     * statutory figure, so it has no legal citation to mirror.
     */
    public function bankFeeAmount(): float
    {
        return (float) Setting::get('payroll_bank_fee_amount', self::DEFAULT_BANK_FEE_AMOUNT);
    }

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
            // Gratuity is posted once a year, in its own closing voucher —
            // see EndOfServiceGratuityClosingVoucherService.
            ->where('kind', '!=', PayslipLineKind::EMPLOYER_COST)
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

            // EMPLOYER_COST lines never reach here — the query above excludes
            // them, since gratuity posts only in the annual closing voucher.
            match ($first->kind) {
                PayslipLineKind::EARNING => $debits[] = ['account' => $account, 'detail' => $detail, 'amount' => $amount],
                PayslipLineKind::DEDUCTION => $credits[] = ['account' => $account, 'detail' => $detail, 'amount' => $amount],
                PayslipLineKind::EMPLOYER_COST => null,
            };
        }

        $netPay = round((float) $run->payslips()->sum('net_pay'), 2);
        $bankFee = round($this->bankFeeAmount(), 2);

        if ($bankFee > 0) {
            $debits[] = ['account' => self::GL_BANK_FEES_EXPENSE, 'detail' => null, 'amount' => $bankFee];
        }

        // The bank fee is settled in the same transfer as net pay, so it
        // folds into the one payable line rather than opening a second
        // payable account for the office to reconcile.
        $payable = round($netPay + $bankFee, 2);

        if ($payable > 0) {
            $credits[] = ['account' => self::GL_NET_SALARY_PAYABLE, 'detail' => null, 'amount' => $payable];
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
