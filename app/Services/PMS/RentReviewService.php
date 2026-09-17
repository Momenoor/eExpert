<?php

namespace App\Services\PMS;

use App\Models\Contract;
use Carbon\CarbonInterface;

/**
 * Evaluating a contract's renewal against two UAE statutes — scoped
 * strictly to that: not a general pricing service, and it changes nothing
 * on the contract itself. Every figure it returns is a proposal for the
 * office to act on (or not) through the normal contract-renewal workflow.
 */
class RentReviewService
{
    /**
     * Federal Decree-Law No. 33 of 2008: rent adjustments cannot be
     * proposed within this many days of the contract's own end date.
     */
    private const NOTICE_DAYS = 90;

    public function evaluateRenewal(Contract $contract, CarbonInterface $targetRenewalDate, float $marketAverageRent): RenewalEvaluation
    {
        $currentRent = (float) $contract->getAttribute('total_base_rent');
        $percentBelowMarket = $this->percentBelowMarket($currentRent, $marketAverageRent);
        $allowedIncreasePercent = $this->resolveRentGapBand($percentBelowMarket);
        $maxAllowableRent = round($currentRent * (1 + $allowedIncreasePercent / 100), 2);

        $isWithinNoticeWindow = $this->isWithinNoticeWindow($targetRenewalDate, $contract->getAttribute('end_date'));

        return new RenewalEvaluation(
            currentRent: $currentRent,
            marketAverageRent: $marketAverageRent,
            percentBelowMarket: round($percentBelowMarket, 2),
            allowedIncreasePercent: $allowedIncreasePercent,
            maxAllowableRent: $maxAllowableRent,
            isWithinNoticeWindow: $isWithinNoticeWindow,
            nonComplianceMessage: $isWithinNoticeWindow ? null : __(
                'Rent adjustments cannot be proposed within :days days of contract expiry (Federal Decree-Law No. 33 of 2008).',
                ['days' => self::NOTICE_DAYS],
            ),
        );
    }

    /**
     * How far current rent sits below the market average, as a percentage —
     * never negative: a rent already at or above market gets the 0% band,
     * not a "negative gap" that would otherwise map to the same band anyway.
     */
    private function percentBelowMarket(float $currentRent, float $marketAverageRent): float
    {
        if ($marketAverageRent <= 0.0) {
            return 0.0;
        }

        return max(0.0, (($marketAverageRent - $currentRent) / $marketAverageRent) * 100);
    }

    /**
     * The five RERA Rental Index bands (Dubai Law No. 43 of 2013).
     */
    private function resolveRentGapBand(float $percentBelowMarket): float
    {
        return match (true) {
            $percentBelowMarket <= 10.0 => 0.0,
            $percentBelowMarket <= 20.0 => 5.0,
            $percentBelowMarket <= 30.0 => 10.0,
            $percentBelowMarket <= 40.0 => 15.0,
            default => 20.0,
        };
    }

    /**
     * Whether at least `NOTICE_DAYS` remain between the proposal date and
     * the contract's end date.
     */
    private function isWithinNoticeWindow(CarbonInterface $targetRenewalDate, CarbonInterface $contractEnd): bool
    {
        return $targetRenewalDate->copy()->addDays(self::NOTICE_DAYS)->lte($contractEnd);
    }
}
