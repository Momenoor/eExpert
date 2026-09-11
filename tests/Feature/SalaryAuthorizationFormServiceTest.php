<?php

namespace Tests\Feature;

use App\Enums\PayrollRunStatus;
use App\Enums\SalaryComponent;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\Party;
use App\Models\PayrollRun;
use App\Models\Setting;
use App\Services\PayrollService;
use App\Services\SalaryAuthorizationFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bank's own salary-upload form for a payroll run — a different document
 * from the accounting journal voucher, built from frozen payslip figures and
 * carrying per-employee bank details rather than GL accounts.
 */
class SalaryAuthorizationFormServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payroll;

    private SalaryAuthorizationFormService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();

        $this->payroll = app(PayrollService::class);
        $this->service = app(SalaryAuthorizationFormService::class);
    }

    private function employee(array $profile = []): Party
    {
        $party = Party::factory()->employee()->create(['name' => 'Maha Abbas', 'phone' => ['0521993422']]);

        EmployeeProfile::create([
            'party_id' => $party->id,
            'date_of_joining' => '2020-01-01',
            'mohre_personal_no' => '00112127965860',
            'bank_name' => 'Emirates Islamic Bank',
            'bank_account_no' => '3578203010002',
            'iban' => 'AE280340003578203010002',
            'wps_routing_code' => '703420114',
            ...$profile,
        ]);

        EmployeeSalaryComponent::create([
            'party_id' => $party->id,
            'component' => SalaryComponent::BASIC->value,
            'amount' => 40200,
            'effective_from' => '2020-01-01',
        ]);

        return $party->fresh();
    }

    private function payrollRun(string $period = '2026-08'): PayrollRun
    {
        return PayrollRun::create(['period' => $period, 'status' => PayrollRunStatus::DRAFT]);
    }

    public function test_it_lists_one_line_per_employee_with_bank_details_and_net_pay(): void
    {
        $this->employee();

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $form = $this->service->forRun($run->fresh());

        $this->assertCount(1, $form['lines']);
        $this->assertSame(1, $form['employee_count']);

        $line = $form['lines'][0];
        $this->assertSame('00112127965860', $line['personal_no']);
        $this->assertSame('Maha Abbas', $line['employee_name']);
        $this->assertSame('Emirates Islamic Bank', $line['bank_name']);
        $this->assertSame('3578203010002', $line['account_no']);
        $this->assertSame('AE280340003578203010002', $line['iban']);
        $this->assertSame('703420114', $line['routing_code']);
        $this->assertSame('0521993422', $line['mobile']);
        $this->assertSame(40200.0, $line['salary']);
    }

    public function test_the_period_is_formatted_in_english_month_slash_year(): void
    {
        $this->employee();

        $run = $this->payrollRun('2026-08');
        $this->payroll->generate($run);

        $form = $this->service->forRun($run->fresh());

        $this->assertSame('August/2026', $form['period']);
    }

    public function test_the_bank_fee_setting_is_split_back_into_charge_and_vat(): void
    {
        $this->employee();
        Setting::set('payroll_bank_fee_amount', 105);

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $form = $this->service->forRun($run->fresh());

        // 105 quoted VAT-inclusive at 5% splits into 100 + 5.
        $this->assertSame(100.0, $form['service_charge']);
        $this->assertSame(5.0, $form['vat']);
        $this->assertSame(40200.0, $form['total_salary']);
        $this->assertSame(40305.0, $form['total_amount']);
    }

    public function test_an_employee_opted_out_is_excluded_from_the_form_and_its_totals(): void
    {
        $this->employee();
        $this->employee(['include_in_salary_authorization_form' => false]);

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        $form = $this->service->forRun($run->fresh());

        $this->assertCount(1, $form['lines']);
        $this->assertSame(1, $form['employee_count']);
        $this->assertSame(40200.0, $form['total_salary']);
    }

    public function test_falls_back_to_the_live_profile_when_a_payslip_has_no_bank_snapshot(): void
    {
        $party = $this->employee();

        $run = $this->payrollRun();
        $this->payroll->generate($run);

        // Simulate a payslip generated before the snapshot columns existed.
        $run->fresh()->payslips()->where('party_id', $party->id)->update([
            'bank_name_snapshot' => null,
            'iban_snapshot' => null,
        ]);

        $form = $this->service->forRun($run->fresh());

        $this->assertSame('Emirates Islamic Bank', $form['lines'][0]['bank_name']);
        $this->assertSame('AE280340003578203010002', $form['lines'][0]['iban']);
    }
}
