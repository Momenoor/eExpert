<?php

namespace App\Services;

use App\Enums\LeaveType;
use App\Models\LeaveEntitlement;
use App\Models\Party;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Leave balances under Federal Decree-Law 33/2021, Article 29 and Article 31.
 *
 * Everything here is keyed to the ANNIVERSARY of joining, not the calendar year.
 * Article 31's sick-leave ladder — 15 days at full pay, then 30 at half, then 45
 * unpaid — resets per year of service, so an employee who joined in September
 * gets a fresh ladder each September. Anchoring it to January instead would hand
 * a second full allowance to anyone who fell ill either side of new year.
 */
class LeaveEntitlementService
{
    /**
     * Statutory defaults from Articles 29 and 31, used whenever Payroll Settings
     * has no override. These are law, not office policy — the settings screen
     * exists for the day the law itself changes, not as an everyday dial.
     */
    private const DEFAULT_SICK_FULL_DAYS = 15;

    private const DEFAULT_SICK_HALF_DAYS = 30;

    private const DEFAULT_SICK_UNPAID_DAYS = 45;

    private const DEFAULT_ANNUAL_DAYS = 30;

    private const DEFAULT_ANNUAL_DAYS_PER_MONTH = 2;

    /** Full-pay sick days available in a year of service. */
    public function sickFullDays(): float
    {
        return (float) Setting::get('payroll_sick_full_pay_days', self::DEFAULT_SICK_FULL_DAYS);
    }

    /** Half-pay sick days available once the full-pay band is exhausted. */
    public function sickHalfDays(): float
    {
        return (float) Setting::get('payroll_sick_half_pay_days', self::DEFAULT_SICK_HALF_DAYS);
    }

    /** Unpaid sick days available once the half-pay band is exhausted. */
    public function sickUnpaidDays(): float
    {
        return (float) Setting::get('payroll_sick_unpaid_days', self::DEFAULT_SICK_UNPAID_DAYS);
    }

    /** Annual leave for a completed year of service. */
    public function annualDays(): float
    {
        return (float) Setting::get('payroll_annual_leave_days', self::DEFAULT_ANNUAL_DAYS);
    }

    /** Monthly annual-leave accrual between six and twelve months of service. */
    public function annualDaysPerMonth(): float
    {
        return (float) Setting::get('payroll_annual_leave_days_per_month', self::DEFAULT_ANNUAL_DAYS_PER_MONTH);
    }

    /**
     * The start of the service year containing a given date.
     */
    public function serviceYearStart(CarbonInterface $joinedOn, CarbonInterface $on): CarbonImmutable
    {
        $anniversary = CarbonImmutable::parse($joinedOn->toDateString());

        if ($on->lessThan($anniversary)) {
            return $anniversary;
        }

        // Walking rather than arithmetic on years so that a 29 February joining
        // date lands on 28 February in common years instead of overflowing into
        // March, which would shift the whole ladder by a day every four years.
        while (true) {
            $next = $anniversary->addYear();

            if ($next->greaterThan($on)) {
                return $anniversary;
            }

            $anniversary = $next;
        }
    }

    /**
     * Annual leave earned by a given point in the service year.
     *
     * Article 29 gives two days a month between six and twelve months of
     * service, and thirty days a year thereafter.
     */
    public function annualEntitlement(CarbonInterface $joinedOn, CarbonInterface $on): float
    {
        $months = $joinedOn->diffInMonths($on);

        if ($months >= 12) {
            return $this->annualDays();
        }

        if ($months >= 6) {
            return (float) $months * $this->annualDaysPerMonth();
        }

        return 0.0;
    }

    /**
     * The balance row for the service year containing $on, created if absent.
     */
    public function forDate(Party $party, CarbonInterface $on): LeaveEntitlement
    {
        $joinedOn = $party->employeeProfile?->getAttribute('date_of_joining');

        // Without a joining date there is no anniversary to hang a year on, so
        // the service year is taken to start at the date in question: balances
        // still accumulate, and correcting the profile later re-anchors them.
        $yearStart = $joinedOn === null
            ? CarbonImmutable::parse($on->toDateString())->startOfYear()
            : $this->serviceYearStart($joinedOn, $on);

        return LeaveEntitlement::firstOrCreate(
            [
                'party_id' => $party->getKey(),
                // Passed as a date object, not a 'Y-m-d' string. The cast writes
                // the column as 'Y-m-d H:i:s', so a string lookup matches nothing
                // on SQLite and firstOrCreate inserts a second row every call —
                // straight into the unique index. MySQL hides this by coercing.
                'service_year_start' => $yearStart,
            ],
            [
                'annual_entitled_days' => $joinedOn === null
                    ? $this->annualDays()
                    : $this->annualEntitlement($joinedOn, $on),
                // Stated rather than left to the column defaults: a freshly
                // created model does not read them back, so the caller would get
                // nulls where it expects balances.
                'annual_taken_days' => 0,
                'sick_full_taken' => 0,
                'sick_half_taken' => 0,
                'sick_unpaid_taken' => 0,
            ],
        );
    }

    /**
     * How a run of sick days falls across Article 31's three pay bands.
     *
     * Returned in ladder order and skipping empty bands, so a two-day absence by
     * someone with one full-pay day left comes back as one full-pay day and one
     * half-pay day — which is exactly the period split the request needs.
     *
     * @return array<int, array{leave_type: LeaveType, day_count: float}>
     */
    public function splitSickDays(LeaveEntitlement $entitlement, float $days): array
    {
        $remaining = [
            LeaveType::SICK_FULL->value => max(0.0, $this->sickFullDays() - (float) $entitlement->getAttribute('sick_full_taken')),
            LeaveType::SICK_HALF->value => max(0.0, $this->sickHalfDays() - (float) $entitlement->getAttribute('sick_half_taken')),
            LeaveType::SICK_UNPAID->value => max(0.0, $this->sickUnpaidDays() - (float) $entitlement->getAttribute('sick_unpaid_taken')),
        ];

        $split = [];

        foreach ($remaining as $type => $available) {
            if ($days <= 0) {
                break;
            }

            $taken = min($days, $available);

            if ($taken <= 0) {
                continue;
            }

            $split[] = [
                'leave_type' => LeaveType::from($type),
                'day_count' => round($taken, 1),
            ];

            $days = round($days - $taken, 1);
        }

        // Past 90 days in a service year the statute stops providing for sick
        // leave at all; anything further is ordinary unpaid leave.
        if ($days > 0) {
            $split[] = [
                'leave_type' => LeaveType::UNPAID,
                'day_count' => round($days, 1),
            ];
        }

        return $split;
    }
}
