<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * The bank's own "Salary Authorization and Upload Form" (WPS) for one payroll
 * run — a different document from the accounting Journal Voucher: this one is
 * what gets signed, stamped and emailed to the exchange house to actually move
 * the money, so it carries bank details per employee rather than GL accounts.
 *
 * Always built in English regardless of the panel's locale — it is a bank
 * form the exchange house reads, not an internal document.
 */
class SalaryAuthorizationFormService
{
    /**
     * UAE's standard rate. `payroll_bank_fee_amount` (Payroll Settings) is
     * quoted VAT-inclusive by the bank, so this is only used to split that one
     * figure back into a charge and a VAT line for the form — it is not an
     * independent setting of its own.
     */
    private const VAT_RATE = 0.05;

    /**
     * @return array{
     *     company_name: string,
     *     employer_id: string|null,
     *     trade_license: string|null,
     *     gl_number: string|null,
     *     period: string,
     *     lines: list<array{
     *         personal_no: string|null,
     *         employee_name: string,
     *         bank_name: string|null,
     *         account_no: string|null,
     *         iban: string|null,
     *         routing_code: string|null,
     *         mobile: string|null,
     *         salary: float,
     *     }>,
     *     employee_count: int,
     *     total_salary: float,
     *     service_charge: float,
     *     vat: float,
     *     total_amount: float,
     * }
     */
    public function forRun(PayrollRun $run): array
    {
        $payslips = $run->payslips()
            ->with('party.employeeProfile')
            ->orderBy('id')
            ->get()
            // Anyone paid outside this batch — by cheque, or through a
            // different exchange — is opted out on their own profile, so
            // neither their bank details nor their salary belong in this
            // form's totals.
            ->filter(fn (Payslip $payslip): bool => $payslip->party?->employeeProfile
                ?->getAttribute('include_in_salary_authorization_form') ?? true);

        $lines = $payslips->map(fn (Payslip $payslip): array => $this->line($payslip))->values()->all();

        $totalSalary = round(array_sum(array_column($lines, 'salary')), 2);

        // Quoted VAT-inclusive by the bank; split back out so the form's own
        // two rows (charge, then VAT) add up to what it will actually charge.
        $feeInclusiveOfVat = round((float) Setting::get('payroll_bank_fee_amount', 0.0), 2);
        $serviceCharge = round($feeInclusiveOfVat / (1 + self::VAT_RATE), 2);
        $vat = round($feeInclusiveOfVat - $serviceCharge, 2);

        return [
            'company_name' => Setting::get('company_name') ?: config('app.name'),
            'employer_id' => Setting::get('payroll_wps_employer_id'),
            'trade_license' => Setting::get('payroll_wps_trade_license'),
            'gl_number' => Setting::get('payroll_wps_gl_number'),
            'period' => Carbon::createFromFormat('Y-m', $run->period)->format('F/Y'),
            'lines' => $lines,
            'employee_count' => count($lines),
            'total_salary' => $totalSalary,
            'service_charge' => $serviceCharge,
            'vat' => $vat,
            'total_amount' => round($totalSalary + $feeInclusiveOfVat, 2),
        ];
    }

    /**
     * @return array{personal_no: string|null, employee_name: string, bank_name: string|null, account_no: string|null, iban: string|null, routing_code: string|null, mobile: string|null, salary: float}
     */
    private function line(Payslip $payslip): array
    {
        $party = $payslip->party;
        $profile = $party?->employeeProfile;

        return [
            'personal_no' => $profile?->getAttribute('mohre_personal_no') ?: $profile?->getAttribute('labour_card_no'),
            // display_name overrides the party's own name only when set — most
            // employees never fill it in, and an empty WPS name on the bank
            // form is worse than the codebase's usual name.
            'employee_name' => $profile?->getAttribute('display_name') ?: ($party?->name ?? ''),
            // The bank name/IBAN frozen on the payslip at generation time, so a
            // bank change afterward does not silently rewrite a batch already
            // sent to the exchange house — falling back to the live profile
            // only for a payslip generated before those snapshots existed.
            'bank_name' => $payslip->bank_name_snapshot ?? $profile?->getAttribute('bank_name'),
            'account_no' => $profile?->getAttribute('bank_account_no'),
            'iban' => $payslip->iban_snapshot ?? $profile?->getAttribute('iban'),
            'routing_code' => $profile?->getAttribute('wps_routing_code'),
            'mobile' => is_array($party?->phone) ? ($party->phone[0] ?? null) : null,
            'salary' => (float) $payslip->net_pay,
        ];
    }
}
