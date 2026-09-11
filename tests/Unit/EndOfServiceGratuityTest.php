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
 *
 * Dates drive every calculation here (not raw day counts) because a part-year
 * is priced by real calendar months and the real length of whichever month it
 * lands in — a day-count-only API cannot express that.
 */
class EndOfServiceGratuityTest extends TestCase
{
    private EndOfServiceGratuityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EndOfServiceGratuityService;
    }

    /**
     * The date that is the Nth day of service — day 1 is the joining day
     * itself, matching `serviceDays()`'s own inclusive convention.
     */
    private function serviceDay(Carbon $joined, int $day): Carbon
    {
        return $joined->copy()->addDays($day - 1);
    }

    public function test_nothing_is_owed_below_one_completed_year(): void
    {
        $joined = Carbon::parse('2020-01-01');

        $this->assertSame(0.0, $this->service->gratuityAsOf($joined, $this->serviceDay($joined, 364), 6000.0));
    }

    public function test_one_year_earns_twenty_one_over_thirty_of_basic(): void
    {
        $joined = Carbon::parse('2020-01-01');

        // 21/30 × 6,000 = 4,200.
        $this->assertSame(4200.0, $this->service->gratuityAsOf($joined, $joined->copy()->addYear(), 6000.0));
    }

    public function test_five_years_earn_twenty_one_over_thirty_for_each(): void
    {
        $joined = Carbon::parse('2020-01-01');

        // 5 × 21/30 × 6,000 = 21,000.
        $this->assertSame(21000.0, $this->service->gratuityAsOf($joined, $joined->copy()->addYears(5), 6000.0));
    }

    public function test_years_beyond_the_fifth_earn_a_full_months_basic(): void
    {
        $joined = Carbon::parse('2020-01-01');

        // First five years: 5 × 21/30 × 6,000 = 21,000.
        // Years six to eight: 3 × 6,000 = 18,000.
        $this->assertSame(39000.0, $this->service->gratuityAsOf($joined, $joined->copy()->addYears(8), 6000.0));
    }

    public function test_part_years_are_priced_by_calendar_months_not_rounded_down(): void
    {
        $joined = Carbon::parse('2020-01-01');

        // A year and a half: one full year (4,200) plus six completed months
        // of the second year (6/12 of 4,200 = 2,100).
        $eighteenMonths = $joined->copy()->addYear()->addMonths(6);

        $gratuity = $this->service->gratuityAsOf($joined, $eighteenMonths, 6000.0);

        $this->assertSame(6300.0, $gratuity);
        $this->assertGreaterThan(
            $this->service->gratuityAsOf($joined, $joined->copy()->addYear(), 6000.0),
            $gratuity,
        );
    }

    public function test_the_entitlement_is_capped_at_two_years_of_salary(): void
    {
        $joined = Carbon::parse('2000-01-01');

        // Thirty years: 5 × 21/30 × 6,000 = 21,000, plus 25 × 6,000 = 150,000
        // — 171,000 total, but the statutory ceiling is 24 × 6,000 = 144,000.
        $this->assertSame(144000.0, $this->service->gratuityAsOf($joined, $joined->copy()->addYears(30), 6000.0));
        $this->assertSame(144000.0, $this->service->cap(6000.0));
    }

    public function test_once_capped_a_further_period_accrues_nothing(): void
    {
        $joined = Carbon::parse('2000-01-01');
        $thirtyYears = $joined->copy()->addYears(30);

        $accrual = $this->service->accrualBetween(
            $joined,
            $thirtyYears->copy()->startOfMonth(),
            $thirtyYears->copy()->endOfMonth(),
            6000.0,
        );

        $this->assertSame(0.0, $accrual);
    }

    public function test_the_period_the_first_year_completes_books_the_whole_first_year(): void
    {
        $joined = Carbon::parse('2025-01-01');

        // Day 350 is worth nothing; day 380 is worth a full first year and a
        // fraction more. The accrual for that period is therefore the entire
        // entitlement so far, not a slice of it.
        $periodStart = $this->serviceDay($joined, 351);
        $periodEnd = $this->serviceDay($joined, 380);

        $accrual = $this->service->accrualBetween($joined, $periodStart, $periodEnd, 6000.0);

        $this->assertGreaterThan(4200.0, $accrual);
        $this->assertSame($this->service->gratuityAsOf($joined, $periodEnd, 6000.0), $accrual);
    }

    public function test_a_mid_service_month_accrues_a_months_worth(): void
    {
        $joined = Carbon::parse('2020-01-01');
        $thirdYear = $joined->copy()->addYears(3);

        // Within the first five years, one completed month is 1/12 of the
        // annual 21/30 rate: (21/30 × 6,000) / 12 = 350.
        $accrual = $this->service->accrualBetween(
            $joined,
            $thirdYear->copy()->startOfMonth(),
            $thirdYear->copy()->endOfMonth(),
            6000.0,
        );

        $this->assertEqualsWithDelta(350.0, $accrual, 15.0);
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
        $joined = Carbon::parse('2020-01-01');
        $threeYears = $joined->copy()->addYears(3);

        // Three years at 6,000 basic: 3 × 21/30 × 6,000 = 12,600.
        $this->assertSame(12600.0, $this->service->gratuityAsOf($joined, $threeYears, 6000.0));

        // Had the same tenure been computed on a 10,000 package, the office
        // would be provisioning 21,000 — two thirds more than it owes.
        $this->assertSame(21000.0, $this->service->gratuityAsOf($joined, $threeYears, 10000.0));
    }

    /**
     * The office's own worked example: resignation under five years of
     * service, joined and left mid-month.
     *
     * Join 2023-01-01, end 2026-05-21, basic 10,000. Three full years
     * (2023 -> 2026-01-01) plus four completed months (Jan-Apr) plus 21 of
     * May's 31 days.
     */
    public function test_worked_example_resignation_under_five_years(): void
    {
        $joined = Carbon::parse('2023-01-01');
        $end = Carbon::parse('2026-05-21');
        $basic = 10000.0;

        $fullYearsGratuity = $this->service->gratuityAsOf($joined, $joined->copy()->addYears(3), $basic);
        $this->assertSame(21000.0, $fullYearsGratuity);

        $total = $this->service->gratuityAsOf($joined, $end, $basic);

        // 21,000 for the three full years, plus (4/12 + (21/31)/12) × 7,000 —
        // a hair over the office's own hand-rounded 23,728.23, since 21/31
        // does not terminate at the precision a manual calculation used.
        $this->assertEqualsWithDelta(23728.49, $total, 0.01);
    }

    /**
     * The office's own worked example: service past five years, joined and
     * left mid-month.
     *
     * Join 2020-01-01, end 2026-05-21, basic 10,000. Six full years
     * (2020 -> 2026-01-01) plus the same four-months-and-21-days remainder,
     * now priced at the beyond-five rate since six years is already past it.
     */
    public function test_worked_example_service_exceeding_five_years(): void
    {
        $joined = Carbon::parse('2020-01-01');
        $end = Carbon::parse('2026-05-21');
        $basic = 10000.0;

        $firstFiveYears = $this->service->gratuityAsOf($joined, $joined->copy()->addYears(5), $basic);
        $this->assertSame(35000.0, $firstFiveYears);

        $sixFullYears = $this->service->gratuityAsOf($joined, $joined->copy()->addYears(6), $basic);
        $this->assertSame(45000.0, $sixFullYears);

        $total = $this->service->gratuityAsOf($joined, $end, $basic);

        // 45,000 for the six full years, plus (4/12 + (21/31)/12) × 10,000 —
        // a hair over the office's own hand-rounded 48,897.50, for the same
        // rounding reason as the example above.
        $this->assertEqualsWithDelta(48897.85, $total, 0.01);
    }

    public function test_a_full_calendar_year_is_worth_exactly_one_month_beyond_the_fifth(): void
    {
        $joined = Carbon::parse('2015-01-01');
        $basic = 5000.0;

        // Years 6 -> 7, both entirely past the five-year mark: exactly one
        // month's basic, not a fraction shaved off by day-count fencepost
        // errors.
        $accrual = $this->service->gratuityAsOf($joined, $joined->copy()->addYears(7), $basic)
            - $this->service->gratuityAsOf($joined, $joined->copy()->addYears(6), $basic);

        $this->assertSame(5000.0, $accrual);
    }
}
