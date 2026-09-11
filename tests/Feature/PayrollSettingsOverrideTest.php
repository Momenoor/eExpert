<?php

namespace Tests\Feature;

use App\Enums\LeaveType;
use App\Models\LeaveEntitlement;
use App\Models\Setting;
use App\Services\EndOfServiceGratuityService;
use App\Services\LeaveEntitlementService;
use App\Services\LoanScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Proves the payroll engine actually reads Payroll Settings, not just its own
 * hardcoded defaults.
 *
 * Every one of these constants moved from a class constant to
 * Setting::get('payroll_xxx', self::DEFAULT_XXX) in the same change, and a
 * fallback that silently never gets consulted is worse than no setting at all —
 * the admin screen would look like it works while every calculation kept using
 * the old number. This is a Feature test (not Unit) specifically so the real
 * `settings` table is migrated and Setting::set() has somewhere to write.
 */
class PayrollSettingsOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setting's in-memory runtime cache is a static property that outlives
        // RefreshDatabase's per-test rollback — clear it so a value set in one
        // test can't leak into the next.
        Setting::clearCache();
    }

    public function test_the_loan_rounding_step_is_configurable(): void
    {
        Setting::set('payroll_loan_rounding_step', 25, 'payroll');

        $schedule = app(LoanScheduleService::class)->plan(1000.0, 4, '2026-01');

        // 1,000 / 4 = 250, already a multiple of 25 — the point is that a step of
        // 25 rather than the 50 default is actually what got applied, provable
        // only via a principal/term combination where the two rounding steps
        // would disagree. 900 / 4 = 225 rounds to 225 at a 25 step, 200 at 50.
        $schedule900 = app(LoanScheduleService::class)->plan(900.0, 4, '2026-01');

        $this->assertSame(225.0, $schedule900[1]['amount']);
        $this->assertNotSame(200.0, $schedule900[1]['amount']);
    }

    public function test_the_loan_rounding_step_falls_back_to_fifty_when_unset(): void
    {
        $schedule = app(LoanScheduleService::class)->plan(900.0, 4, '2026-01');

        $this->assertSame(200.0, $schedule[1]['amount']);
    }

    public function test_the_gratuity_days_per_month_is_configurable(): void
    {
        Setting::set('payroll_days_per_month', 28, 'payroll');

        $service = app(EndOfServiceGratuityService::class);

        // 6,000 / 28 rather than / 30 — a different, provably-non-default rate.
        $this->assertEqualsWithDelta(6000.0 / 28, $service->dailyRate(6000.0), 0.0001);
    }

    public function test_the_gratuity_cap_is_configurable(): void
    {
        Setting::set('payroll_eosg_cap_months', 18, 'payroll');

        $service = app(EndOfServiceGratuityService::class);

        $this->assertSame(6000.0 * 18, $service->cap(6000.0));
    }

    public function test_the_first_five_year_band_is_configurable(): void
    {
        Setting::set('payroll_eosg_days_per_year_first_five', 30, 'payroll');

        $service = app(EndOfServiceGratuityService::class);
        $joined = Carbon::parse('2020-01-01');

        // 30 days of basic at 6,000/30 = 200/day: 6,000 flat — different from
        // the statutory default of 21 days (4,200).
        $this->assertSame(6000.0, $service->gratuityAsOf($joined, $joined->copy()->addYear(), 6000.0));
    }

    public function test_years_beyond_the_fifth_are_a_full_months_salary_regardless_of_days_per_month(): void
    {
        // The days-per-month setting is shared with the daily rate used for
        // the first five years and for unpaid leave — it must not also creep
        // into what "a year beyond the fifth" is worth, since the statute
        // means a literal month's salary there, not a day count.
        Setting::set('payroll_days_per_month', 28, 'payroll');

        $service = app(EndOfServiceGratuityService::class);
        $joined = Carbon::parse('2020-01-01');

        // Six years: 5 at 21/28 days' basic (first five), plus exactly one
        // full month's basic (6,000) for the sixth — not 30/28 days' worth.
        $this->assertSame(
            6000.0,
            $service->gratuityAsOf($joined, $joined->copy()->addYears(6), 6000.0)
                - $service->gratuityAsOf($joined, $joined->copy()->addYears(5), 6000.0),
        );
    }

    public function test_the_minimum_service_before_anything_is_owed_is_configurable(): void
    {
        Setting::set('payroll_eosg_minimum_service_years', 2, 'payroll');

        $service = app(EndOfServiceGratuityService::class);
        $joined = Carbon::parse('2020-01-01');

        // One year alone is no longer enough once the office requires two.
        $this->assertSame(0.0, $service->gratuityAsOf($joined, $joined->copy()->addYear(), 6000.0));

        // Once the (now two-year) threshold is crossed, the WHOLE period
        // since joining is paid — not just the time after year one.
        $this->assertGreaterThan(0.0, $service->gratuityAsOf($joined, $joined->copy()->addYears(2), 6000.0));
    }

    public function test_the_annual_leave_entitlement_is_configurable(): void
    {
        Setting::set('payroll_annual_leave_days', 22, 'payroll');

        $service = app(LeaveEntitlementService::class);

        $this->assertSame(
            22.0,
            $service->annualEntitlement(
                Carbon::parse('2020-01-01'),
                Carbon::parse('2026-01-01'),
            ),
        );
    }

    public function test_the_sick_leave_bands_are_configurable(): void
    {
        Setting::set('payroll_sick_full_pay_days', 10, 'payroll');
        Setting::set('payroll_sick_half_pay_days', 20, 'payroll');
        Setting::set('payroll_sick_unpaid_days', 30, 'payroll');

        $service = app(LeaveEntitlementService::class);

        $entitlement = new LeaveEntitlement([
            'sick_full_taken' => 0,
            'sick_half_taken' => 0,
            'sick_unpaid_taken' => 0,
        ]);

        // Fifteen days of sickness against a reduced 10-day full-pay band: ten
        // full, five half — the statutory default (15 full) would have kept the
        // whole run at full pay instead.
        $split = $service->splitSickDays($entitlement, 15);

        $this->assertSame(
            [[LeaveType::SICK_FULL, 10.0], [LeaveType::SICK_HALF, 5.0]],
            array_map(fn (array $part): array => [$part['leave_type'], $part['day_count']], $split),
        );
    }
}
