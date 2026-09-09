<?php

namespace Tests\Unit;

use App\Services\EndOfServiceGratuityService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * End-of-service gratuity, Federal Decree-Law 33/2021 Article 51.
 *
 * The figures below are all computed on BASIC salary at a thirty-day month,
 * because that is what the statute says and because getting it wrong is
 * expensive in exactly one direction: paying gratuity on gross would overstate
 * the liability by whatever housing and transport come to, which in this office
 * is a large fraction of the package.
 */
class EndOfServiceGratuityTest extends TestCase
{
    private EndOfServiceGratuityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EndOfServiceGratuityService;
    }

    public function test_nothing_is_owed_below_one_completed_year(): void
    {
        $this->assertSame(0.0, $this->service->gratuityFor(364, 6000.0));
    }

    public function test_one_year_earns_twenty_one_days_of_basic(): void
    {
        // 21 days × (6,000 / 30) = 21 × 200 = 4,200.
        $this->assertSame(4200.0, $this->service->gratuityFor(365, 6000.0));
    }

    public function test_five_years_earn_twenty_one_days_for_each(): void
    {
        // 5 × 21 = 105 days × 200 = 21,000.
        $this->assertSame(21000.0, $this->service->gratuityFor(365 * 5, 6000.0));
    }

    public function test_years_beyond_the_fifth_earn_thirty_days(): void
    {
        // First five years 105 days, plus 3 × 30 = 90 for years six to eight.
        // 195 days × 200 = 39,000.
        $this->assertSame(39000.0, $this->service->gratuityFor(365 * 8, 6000.0));
    }

    public function test_part_years_are_paid_pro_rata_not_rounded_down(): void
    {
        // 547 days is a year and a half: 21 × (547/365) = 31.47 days × 200.
        // Rounding the part-year away would drop the employee back to a flat
        // 4,200 — a silent loss of just over 2,000 AED.
        $this->assertSame(6294.25, $this->service->gratuityFor(547, 6000.0));
        $this->assertGreaterThan($this->service->gratuityFor(365, 6000.0), $this->service->gratuityFor(547, 6000.0));
    }

    public function test_the_entitlement_is_capped_at_two_years_of_salary(): void
    {
        // Thirty years would earn 105 + 750 = 855 days × 200 = 171,000, but the
        // statutory ceiling is 24 × 6,000 = 144,000.
        $this->assertSame(144000.0, $this->service->gratuityFor(365 * 30, 6000.0));
        $this->assertSame(144000.0, $this->service->cap(6000.0));
    }

    public function test_once_capped_a_further_month_accrues_nothing(): void
    {
        $start = 365 * 30;

        $this->assertSame(
            0.0,
            $this->service->monthlyAccrual($start, $start + 30, 6000.0),
        );
    }

    public function test_the_month_the_first_year_completes_books_the_whole_first_year(): void
    {
        // Day 350 is worth nothing; day 380 is worth a full year and change.
        // The accrual for that month is therefore the entire entitlement so far,
        // not a thirtieth of it.
        $accrual = $this->service->monthlyAccrual(350, 380, 6000.0);

        $this->assertGreaterThan(4200.0, $accrual);
        $this->assertSame($this->service->gratuityFor(380, 6000.0), $accrual);
    }

    public function test_a_mid_service_month_accrues_a_months_worth(): void
    {
        // Within the first five years, a month adds 21/12 = 1.75 days of basic:
        // 1.75 × 200 = 350.
        $accrual = $this->service->monthlyAccrual(365 * 3, 365 * 3 + 30, 6000.0);

        $this->assertEqualsWithDelta(345.2, $accrual, 5.0);
    }

    public function test_unpaid_leave_does_not_count_toward_the_service_period(): void
    {
        $joined = Carbon::parse('2025-01-01');
        $asOf = Carbon::parse('2025-12-31');

        $withoutLeave = $this->service->serviceDays($joined, $asOf);
        $withLeave = $this->service->serviceDays($joined, $asOf, 10.0);

        $this->assertSame(365, $withoutLeave);
        $this->assertSame(355, $withLeave);
    }

    public function test_service_before_the_joining_date_is_zero(): void
    {
        $this->assertSame(
            0,
            $this->service->serviceDays(Carbon::parse('2026-01-01'), Carbon::parse('2025-12-31')),
        );
    }

    public function test_gratuity_is_computed_on_basic_alone(): void
    {
        // An employee on 6,000 basic plus 4,000 of allowances, three years in:
        // 3 × 21 = 63 days at 6,000/30 = 200 a day.
        $this->assertSame(12600.0, $this->service->gratuityFor(365 * 3, 6000.0));

        // Had the same tenure been computed on the 10,000 package, the office
        // would be provisioning 21,000 — two thirds more than it owes.
        $this->assertSame(21000.0, $this->service->gratuityFor(365 * 3, 10000.0));
    }
}
