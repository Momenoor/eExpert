<?php

namespace Tests\Feature\PMS;

use App\Models\Lease;
use App\Services\PMS\RentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The RERA rent-increase bands (Dubai Law No. 43 of 2013) and the 90-day
 * pre-expiry notice constraint (Federal Decree-Law No. 33 of 2008) — the
 * only two rules this service exists to apply.
 */
class RentReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RentReviewService::class);
    }

    private function contractRentedAt(float $rent, string $endDate = '2027-01-01'): Lease
    {
        return Lease::factory()->create([
            'total_base_rent' => $rent,
            'end_date' => $endDate,
        ]);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function reraBandProvider(): array
    {
        return [
            'at market (0% gap) allows 0%' => [100000, 100000, 0.0],
            'exactly 10% below allows 0%' => [90000, 100000, 0.0],
            'just past 10% (11%) allows 5%' => [89000, 100000, 5.0],
            'exactly 20% below allows 5%' => [80000, 100000, 5.0],
            'just past 20% (21%) allows 10%' => [79000, 100000, 10.0],
            'exactly 30% below allows 10%' => [70000, 100000, 10.0],
            'just past 30% (31%) allows 15%' => [69000, 100000, 15.0],
            'exactly 40% below allows 15%' => [60000, 100000, 15.0],
            'past 40% (41%) allows 20%' => [59000, 100000, 20.0],
            'far below market (80%) allows 20%' => [20000, 100000, 20.0],
            'above market allows 0%, not negative' => [120000, 100000, 0.0],
        ];
    }

    #[DataProvider('reraBandProvider')]
    public function test_rera_rent_increase_bands(float $currentRent, float $marketAverage, float $expectedAllowedIncrease): void
    {
        $lease = $this->contractRentedAt($currentRent);

        $evaluation = $this->service->evaluateRenewal($lease, Carbon::parse('2026-01-01'), $marketAverage);

        $this->assertSame($expectedAllowedIncrease, $evaluation->allowedIncreasePercent);
    }

    public function test_max_allowable_rent_applies_the_allowed_percentage_to_current_rent(): void
    {
        $lease = $this->contractRentedAt(80000);

        $evaluation = $this->service->evaluateRenewal($lease, Carbon::parse('2026-01-01'), 100000);

        // 20% below market → 5% band → 80,000 * 1.05 = 84,000.
        $this->assertSame(5.0, $evaluation->allowedIncreasePercent);
        $this->assertSame(84000.0, $evaluation->maxAllowableRent);
    }

    public function test_exactly_ninety_days_before_expiry_is_within_the_notice_window(): void
    {
        $lease = $this->contractRentedAt(100000, '2026-04-01');

        // 2026-04-01 minus 90 days = 2026-01-01.
        $evaluation = $this->service->evaluateRenewal($lease, Carbon::parse('2026-01-01'), 100000);

        $this->assertTrue($evaluation->isWithinNoticeWindow);
        $this->assertNull($evaluation->nonComplianceMessage);
    }

    public function test_eighty_nine_days_before_expiry_is_not_compliant(): void
    {
        $lease = $this->contractRentedAt(100000, '2026-04-01');

        // One day later than the 90-day boundary — only 89 days remain.
        $evaluation = $this->service->evaluateRenewal($lease, Carbon::parse('2026-01-02'), 100000);

        $this->assertFalse($evaluation->isWithinNoticeWindow);
        $this->assertNotNull($evaluation->nonComplianceMessage);
    }

    public function test_zero_market_average_is_treated_as_no_gap_rather_than_dividing_by_zero(): void
    {
        $lease = $this->contractRentedAt(50000);

        $evaluation = $this->service->evaluateRenewal($lease, Carbon::parse('2026-01-01'), 0.0);

        $this->assertSame(0.0, $evaluation->percentBelowMarket);
        $this->assertSame(0.0, $evaluation->allowedIncreasePercent);
    }
}
