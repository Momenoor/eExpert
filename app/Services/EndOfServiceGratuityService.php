<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * End-of-service gratuity under Federal Decree-Law 33/2021, Article 51.
 *
 * Four things about the statute drive every method here:
 *
 * 1. It is computed on BASIC salary alone. Housing, transport and utilities are
 *    excluded, which is why nothing in this class ever sees a gross figure.
 * 2. Nothing is owed below one completed year of service (`minimumServiceYears()`,
 *    configurable if office policy or the law itself ever changes it); past
 *    that, the WHOLE period since joining is paid pro rata, not just the time
 *    since the threshold was crossed.
 * 3. The first five years are paid at 21/30 of basic pay per year. Every year
 *    beyond the fifth is paid at a full month's basic salary outright — taken
 *    as `$monthlyBasic` directly rather than "30 days at the daily rate", so it
 *    can never silently drift away from being one month's salary if
 *    `payroll_days_per_month` (shared with the unpaid-leave rate) is ever
 *    changed for an unrelated reason.
 * 4. A part-year is priced by calendar months and, for whatever is left over,
 *    the actual length of that specific calendar month — not by dividing raw
 *    elapsed days by 365. Measuring years as `days ÷ 365` and then differencing
 *    two such figures loses almost a full day every time (the "opening" and
 *    "closing" tallies each count their own reference date, so a full
 *    Jan-1-to-Dec-31 year comes out 364 days long, not 365 — a fencepost the
 *    month-based method below sidesteps entirely). The total is capped at two
 *    years' wage, so a very long tenure stops accruing rather than growing
 *    without limit.
 */
class EndOfServiceGratuityService
{
    /**
     * Statutory defaults from Article 51, used whenever Payroll Settings has no
     * override. These are law, not office policy — the settings screen exists
     * for the day the law itself changes, not as an everyday dial.
     */
    private const DEFAULT_DAYS_PER_YEAR_FIRST_FIVE = 21;

    private const DEFAULT_CAP_MONTHS = 24;

    /**
     * Article 51 works in months of thirty days, whatever the calendar says.
     * Shared with PayrollService's unpaid-leave rate under the same setting key,
     * because both are "basic salary ÷ a month" and must never disagree about
     * what a month is.
     */
    private const DEFAULT_DAYS_PER_MONTH = 30;

    /**
     * Office policy on how many completed years are needed before anything
     * is owed at all. Expressed in years rather than a fixed day count so it
     * reads the same way the office states it.
     */
    private const DEFAULT_MINIMUM_SERVICE_YEARS = 1;

    /** Used only to convert the minimum-service policy into a day threshold. */
    private const DAYS_PER_YEAR_FOR_ELIGIBILITY = 365;

    /** Days of basic pay earned per year for the first five years. */
    public function daysPerYearFirstFive(): float
    {
        return (float) Setting::get('payroll_eosg_days_per_year_first_five', self::DEFAULT_DAYS_PER_YEAR_FIRST_FIVE);
    }

    /** Completed years of service required before any gratuity is owed. */
    public function minimumServiceYears(): float
    {
        return (float) Setting::get('payroll_eosg_minimum_service_years', self::DEFAULT_MINIMUM_SERVICE_YEARS);
    }

    /** The statutory ceiling, expressed in months of basic pay. */
    public function capMonths(): float
    {
        return (float) Setting::get('payroll_eosg_cap_months', self::DEFAULT_CAP_MONTHS);
    }

    public function daysPerMonth(): float
    {
        return (float) Setting::get('payroll_days_per_month', self::DEFAULT_DAYS_PER_MONTH);
    }

    public function dailyRate(float $monthlyBasic): float
    {
        return $monthlyBasic / $this->daysPerMonth();
    }

    public function cap(float $monthlyBasic): float
    {
        return $monthlyBasic * $this->capMonths();
    }

    /**
     * Service days between joining and a reference date, less unpaid days.
     *
     * Unpaid leave does not count toward the service period, so a month with ten
     * unpaid days advances the entitlement by twenty days, not thirty.
     */
    public function serviceDays(CarbonInterface $joinedOn, CarbonInterface $asOf, float $unpaidDays = 0.0): int
    {
        if ($asOf->lessThan($joinedOn)) {
            return 0;
        }

        // Inclusive of both endpoints: joining and leaving on the same day is a
        // day of service, not zero.
        $elapsed = $joinedOn->diffInDays($asOf) + 1;

        return (int) max(0, $elapsed - (int) round($unpaidDays));
    }

    /**
     * Cumulative gratuity entitlement as of a given date — what settling the
     * employee today, at this basic salary, would cost.
     *
     * Full years are counted from each service anniversary (so a 15 March
     * joiner completes years on 15 March, not the calendar year end), each
     * priced at 21/30 of basic for the first five and a full month's basic
     * thereafter. Whatever is left over — less than a full anniversary month —
     * is priced as a fraction of the *current* band's annual rate:
     * completed months since the last anniversary, plus the days served in
     * the current partial month divided by that specific month's real length
     * (28-31, whichever it actually is).
     */
    public function gratuityAsOf(
        CarbonInterface $joinedOn,
        CarbonInterface $asOf,
        float $monthlyBasic,
        float $unpaidDays = 0.0,
    ): float {
        if ($monthlyBasic <= 0) {
            return 0.0;
        }

        $totalDays = $this->serviceDays($joinedOn, $asOf, $unpaidDays);

        if ($totalDays < $this->minimumServiceYears() * self::DAYS_PER_YEAR_FOR_ELIGIBILITY) {
            return 0.0;
        }

        // Reconstructed as a real date so unpaid days delay the anniversary
        // and month milestones below, rather than only shaving the total.
        $effectiveAsOf = $joinedOn->copy()->addDays($totalDays - 1);

        $anniversary = $this->lastAnniversaryOnOrBefore($joinedOn, $effectiveAsOf);
        $fullYears = (int) round($joinedOn->diffInYears($anniversary));

        $monthCursor = $this->lastMonthMarkOnOrBefore($anniversary, $effectiveAsOf);
        $completedMonths = (int) round($anniversary->diffInMonths($monthCursor));

        $nextMonth = $monthCursor->copy()->addMonthNoOverflow();
        $daysInFinalMonth = $monthCursor->diffInDays($nextMonth);

        // Inclusive of the boundary day itself (the 1st of the partial month
        // counts as a day served) — EXCEPT when $effectiveAsOf lands exactly
        // on $monthCursor, i.e. there is no partial month at all. That day
        // already belongs to the completed-months tally above; counting it
        // again here would phantom-add a day every time a period happens to
        // end exactly on a monthly or annual mark.
        $gapDays = (int) round($monthCursor->diffInDays($effectiveAsOf));
        $daysServedInFinalMonth = $gapDays === 0 ? 0 : $gapDays + 1;

        $partialYearFraction = ($completedMonths / 12)
            + (($daysServedInFinalMonth / $daysInFinalMonth) / 12);

        $firstFiveYearRate = $this->daysPerYearFirstFive() / $this->daysPerMonth();

        $firstFiveFullYears = min($fullYears, 5);
        $afterFiveFullYears = max(0, $fullYears - 5);
        $partialYearIsAfterFive = $fullYears >= 5;

        $gratuity = ($firstFiveFullYears * $firstFiveYearRate * $monthlyBasic)
            + ($afterFiveFullYears * $monthlyBasic)
            + ($partialYearFraction * ($partialYearIsAfterFive ? 1.0 : $firstFiveYearRate) * $monthlyBasic);

        return round(min($gratuity, $this->cap($monthlyBasic)), 2);
    }

    /**
     * What a period — a payroll month, or a calendar year with no payslips —
     * adds to the liability.
     *
     * Taken as the difference between the entitlement at the end of the period
     * and at its start (the day BEFORE the period begins, not the period's own
     * first day — counting the first day on both sides is exactly the fencepost
     * this class exists to avoid). That is what makes the crossings behave: the
     * period an employee completes their first year in books the whole first
     * year, the sixth anniversary steps the rate up mid-period without a
     * special case, and once the cap binds the difference falls to zero on its
     * own.
     */
    public function accrualBetween(
        CarbonInterface $joinedOn,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        float $monthlyBasic,
        float $unpaidDaysInPeriod = 0.0,
    ): float {
        $opening = $this->gratuityAsOf($joinedOn, $periodStart->copy()->subDay(), $monthlyBasic);
        $closing = $this->gratuityAsOf($joinedOn, $periodEnd, $monthlyBasic, $unpaidDaysInPeriod);

        return round($closing - $opening, 2);
    }

    /**
     * The most recent service anniversary on or before a date.
     *
     * Walks whole years rather than doing arithmetic on the calendar year, so
     * a 29 February joining date lands on 28 February in common years instead
     * of overflowing into March.
     */
    private function lastAnniversaryOnOrBefore(CarbonInterface $joinedOn, CarbonInterface $on): CarbonInterface
    {
        $anniversary = $joinedOn->copy();

        while ($anniversary->copy()->addYear()->lessThanOrEqualTo($on)) {
            $anniversary = $anniversary->addYear();
        }

        return $anniversary;
    }

    /**
     * The most recent monthly mark (from a starting date) on or before a date.
     */
    private function lastMonthMarkOnOrBefore(CarbonInterface $from, CarbonInterface $on): CarbonInterface
    {
        $cursor = $from->copy();

        while ($cursor->copy()->addMonthNoOverflow()->lessThanOrEqualTo($on)) {
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $cursor;
    }
}
