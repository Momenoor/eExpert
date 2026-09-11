<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\FinancialConfiguration;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();

        // 'super-admin' (hyphenated) is the role name Shield's config actually
        // points at — see config/filament-shield.php.
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Setting::clearCache();

        parent::tearDown();
    }

    public function test_can_render_financial_configuration_page(): void
    {
        $this->get(FinancialConfiguration::getUrl())->assertSuccessful();
    }

    public function test_guest_cannot_access_financial_configuration_page(): void
    {
        auth()->logout();

        $this->get(FinancialConfiguration::getUrl())->assertRedirect();
    }

    public function test_a_non_admin_cannot_access_financial_configuration_page(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        // canAccess() is the barrier, not a hidden nav link — a direct GET must
        // still be refused.
        $this->get(FinancialConfiguration::getUrl())->assertForbidden();
    }

    public function test_the_payroll_cluster_prefills_from_code_defaults_when_nothing_is_saved(): void
    {
        Livewire::test(FinancialConfiguration::class)
            ->assertSchemaStateSet([
                'payroll_loan_rounding_step' => 50,
                'payroll_days_per_month' => 30,
                'payroll_eosg_cap_months' => 24,
                'payroll_eosg_days_per_year_first_five' => 21,
                'payroll_eosg_minimum_service_years' => 1,
                'payroll_annual_leave_days' => 30,
                'payroll_annual_leave_days_per_month' => 2,
                'payroll_sick_full_pay_days' => 15,
                'payroll_sick_half_pay_days' => 30,
                'payroll_sick_unpaid_days' => 45,
            ], 'payrollForm');
    }

    public function test_the_incentive_cluster_prefills_from_code_defaults_when_nothing_is_saved(): void
    {
        Livewire::test(FinancialConfiguration::class)
            ->assertSchemaStateSet([
                'incentive_minimum_matters_per_month' => 3,
                'incentive_below_minimum_penalty_pct' => 2.0,
                'incentive_committee_fixed_percentage' => 8.0,
                'incentive_office_work_adjustment' => 2.0,
            ], 'incentiveForm');
    }

    public function test_can_fill_and_save_payroll_settings(): void
    {
        Livewire::test(FinancialConfiguration::class)
            ->fillForm([
                'payroll_loan_rounding_step' => 25,
                'payroll_days_per_month' => 30,
                'payroll_eosg_cap_months' => 24,
                'payroll_eosg_days_per_year_first_five' => 21,
                'payroll_eosg_minimum_service_years' => 1,
                'payroll_annual_leave_days' => 30,
                'payroll_annual_leave_days_per_month' => 2,
                'payroll_sick_full_pay_days' => 15,
                'payroll_sick_half_pay_days' => 30,
                'payroll_sick_unpaid_days' => 45,
            ], 'payrollForm')
            ->call('savePayroll')
            ->assertHasNoFormErrors();

        // Filament's numeric() TextInput hands back a float; the services already
        // cast through (float) Setting::get(...), so this is the value they see.
        $this->assertEqualsWithDelta(25.0, Setting::get('payroll_loan_rounding_step'), 0.001);
    }

    public function test_the_rounding_step_must_be_at_least_one(): void
    {
        Livewire::test(FinancialConfiguration::class)
            ->fillForm(['payroll_loan_rounding_step' => 0], 'payrollForm')
            ->call('savePayroll')
            ->assertHasFormErrors(['payroll_loan_rounding_step'], 'payrollForm');
    }

    public function test_can_fill_and_save_incentive_settings(): void
    {
        Livewire::test(FinancialConfiguration::class)
            ->fillForm([
                'incentive_minimum_matters_per_month' => 5,
                'incentive_below_minimum_penalty_pct' => 2.0,
                'incentive_committee_fixed_percentage' => 8.0,
                'incentive_office_work_adjustment' => 2.0,
                'incentive_enable_first_review_deduction' => true,
                'incentive_enable_subsequent_review_deduction' => true,
                'incentive_enable_late_report_deduction' => true,
                'incentive_enable_below_minimum_penalty' => true,
                'incentive_enable_court_penalty_exclusion' => true,
            ], 'incentiveForm')
            ->call('saveIncentive')
            ->assertHasNoFormErrors();

        $this->assertEqualsWithDelta(5, Setting::get('incentive_minimum_matters_per_month'), 0.001);
    }

    public function test_a_user_who_can_only_view_payroll_only_sees_the_payroll_cluster(): void
    {
        Role::firstOrCreate(['name' => 'payroll-only', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'View:FinancialConfiguration', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ViewAny:PayrollRun', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('payroll-only');
        $user->givePermissionTo(['View:FinancialConfiguration', 'ViewAny:PayrollRun']);
        $this->actingAs($user);

        Livewire::test(FinancialConfiguration::class)
            ->assertSchemaExists('payrollForm')
            ->assertSchemaComponentDoesNotExist('incentive_minimum_matters_per_month');
    }
}
