<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * End-of-service gratuity under Federal Decree-Law 33/2021, Article 51.
 *
 * Three things about the statute drive every method here:
 *
 * 1. It is computed on BASIC salary alone. Housing, transport and utilities are
 *    excluded, which is why nothing in this class ever sees a gross figure.
 * 2. Nothing is owed below one completed year of service; past that, part-years
 *    are paid pro rata rather than rounded down.
 * 3. The total is capped at two years' wage, so a very long tenure stops
 *    accruing rather than growing without limit.
 */
class EndOfServiceGratuityService
{
    /**
     * Statutory defaults from Article 51, used whenever Payroll Settings has no
     * override. These are law, not office policy — the settings screen exists
     * for the day the law itself changes, not as an everyday dial.
     */
    private const DEFAULT_DAYS_PER_YEAR_FIRST_FIVE = 21;

    private const DEFAULT_DAYS_PER_YEAR_AFTER_FIVE = 30;

    private const DEFAULT_CAP_MONTHS = 24;

    /**
     * Article 51 works in months of thirty days, whatever the calendar says.
     * Shared with PayrollService's unpaid-leave rate under the same setting key,
     * because both are "basic salary ÷ a month" and must never disagree about
     * what a month is.
     */
    private const DEFAULT_DAYS_PER_MONTH = 30;

    private const DAYS_PER_YEAR = 365;

    /** Days of basic pay earned per year for the first five years. */
    public function daysPerYearFirstFive(): float
    {
        return (float) Setting::get('payroll_eosg_days_per_year_first_five', self::DEFAULT_DAYS_PER_YEAR_FIRST_FIVE);
    }

    /** Days of basic pay earned per year beyond the fifth. */
    public function daysPerYearAfterFive(): float
    {
        return (float) Setting::get('payroll_eosg_days_per_year_after_five', self::DEFAULT_DAYS_PER_YEAR_AFTER_FIVE);
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

    /**
     * The gratuity payable for a completed service period.
     *
     * @param  int  $serviceDays  Days of service, already net of unpaid leave
     * @param  float  $monthlyBasic  Basic salary in AED at the date of leaving
     */
    public function gratuityFor(int $serviceDays, float $monthlyBasic): float
    {
        if ($serviceDays < self::DAYS_PER_YEAR || $monthlyBasic <= 0) {
            return 0.0;
        }

        $years = $serviceDays / self::DAYS_PER_YEAR;

        $days = $this->daysPerYearFirstFive() * min($years, 5)
            + $this->daysPerYearAfterFive() * max(0, $years - 5);

        $gratuity = $days * $this->dailyRate($monthlyBasic);

        return round(min($gratuity, $this->cap($monthlyBasic)), 2);
    }

    /**
     * What one month of continued service adds to the liability.
     *
     * Taken as the difference between the entitlement at the end of the month
     * and at its start, rather than as a flat monthly figure. That is what makes
     * the crossings behave: the month an employee completes their first year is
     * the month the whole first year lands, the sixth anniversary steps the rate
     * up mid-month without a special case, and once the two-year cap binds the
     * difference falls to zero on its own.
     *
     * @param  int  $serviceDaysAtStart  Service days as at the first of the month
     * @param  int  $serviceDaysAtEnd  Service days as at the last of the month
     */
    public function monthlyAccrual(int $serviceDaysAtStart, int $serviceDaysAtEnd, float $monthlyBasic): float
    {
        $opening = $this->gratuityFor($serviceDaysAtStart, $monthlyBasic);
        $closing = $this->gratuityFor($serviceDaysAtEnd, $monthlyBasic);

        return round($closing - $opening, 2);
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

    public function dailyRate(float $monthlyBasic): float
    {
        return $monthlyBasic / $this->daysPerMonth();
    }

    public function cap(float $monthlyBasic): float
    {
        return $monthlyBasic * $this->capMonths();
    }
}
