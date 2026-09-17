<?php

namespace Tests\Feature\PMS;

use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\QuotationStatus;
use App\Models\Party;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\PMS\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Generating a quotation, and moving it through its lifecycle. VAT per line
 * is computed from `Unit::vatRate()` and frozen onto the `quotation_unit`
 * pivot at generation time — this is the one place that computation happens.
 */
class QuotationServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();

        $this->service = app(QuotationService::class);
    }

    private function tenant(): Party
    {
        return Party::factory()->tenant()->create();
    }

    public function test_a_quotation_spanning_residential_and_commercial_units_prices_each_line_correctly(): void
    {
        $residential = Unit::factory()->residential()->create();
        $commercial = Unit::factory()->commercial()->create();

        $quotation = $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [
                ['unit_id' => $residential->id, 'offered_rent' => 80000],
                ['unit_id' => $commercial->id, 'offered_rent' => 120000],
            ],
            'security_deposit' => 10000,
            'number_of_installments' => 4,
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->assertSame('200000.00', $quotation->base_rent);
        // Only the commercial line is taxed: 120,000 * 5% = 6,000.
        $this->assertSame('6000.00', $quotation->vat_amount);
        $this->assertSame('206000.00', $quotation->total_amount);
        $this->assertSame(QuotationStatus::DRAFT, $quotation->status);
        $this->assertCount(2, $quotation->units);

        $commercialLine = $quotation->units->firstWhere('id', $commercial->id);
        $this->assertSame(6000.0, (float) $commercialLine->pivot->vat_amount);

        $residentialLine = $quotation->units->firstWhere('id', $residential->id);
        $this->assertSame(0.0, (float) $residentialLine->pivot->vat_amount);
    }

    public function test_generating_with_no_units_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);
    }

    public function test_the_attestation_fee_estimate_is_added_from_settings(): void
    {
        Setting::set('pms_attestation_fee_estimate', 500);

        $unit = Unit::factory()->residential()->create();

        $quotation = $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 50000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->assertSame('500.00', $quotation->attestation_fee_estimate);
        $this->assertSame('50500.00', $quotation->total_amount);
    }

    public function test_payment_schedule_splits_the_total_evenly_with_rounding_absorbed_by_the_last_installment(): void
    {
        $unit = Unit::factory()->create(['property_classification' => PropertyClassification::RESIDENTIAL]);

        $quotation = $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 100]],
            'number_of_installments' => 3,
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $schedule = $this->service->paymentSchedule($quotation);

        $this->assertSame([33.33, 33.33, 33.34], $schedule);
        $this->assertSame(100.0, array_sum($schedule));
    }

    public function test_status_only_moves_forward_through_the_service(): void
    {
        $unit = Unit::factory()->residential()->create();

        $quotation = $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 50000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->service->send($quotation);
        $this->assertSame(QuotationStatus::SENT, $quotation->fresh()->status);

        $this->service->accept($quotation);
        $this->assertSame(QuotationStatus::ACCEPTED, $quotation->fresh()->status);
    }

    public function test_a_draft_cannot_be_accepted_directly(): void
    {
        $unit = Unit::factory()->residential()->create();

        $quotation = $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 50000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->accept($quotation);
    }

    public function test_a_sent_quotation_can_be_rejected(): void
    {
        $unit = Unit::factory()->residential()->create();

        $quotation = $this->service->generate([
            'party_id' => $this->tenant()->id,
            'units' => [['unit_id' => $unit->id, 'offered_rent' => 50000]],
            'validity_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->service->send($quotation);
        $this->service->reject($quotation);

        $this->assertSame(QuotationStatus::REJECTED, $quotation->fresh()->status);
    }
}
