<?php

namespace App\Services\PMS;

/**
 * The result of evaluating a contract's renewal against the RERA rental
 * index and the 90-day notice window — a value object, not a persisted
 * record, since a renewal proposal is a what-if until the office actually
 * acts on it.
 */
final class RenewalEvaluation
{
    public function __construct(
        public readonly float $currentRent,
        public readonly float $marketAverageRent,
        public readonly float $percentBelowMarket,
        public readonly float $allowedIncreasePercent,
        public readonly float $maxAllowableRent,
        public readonly bool $isWithinNoticeWindow,
        public readonly ?string $nonComplianceMessage,
    ) {}
}
