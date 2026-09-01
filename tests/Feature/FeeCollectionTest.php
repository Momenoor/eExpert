<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Enums\MatterCollectionStatus;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fee/allocation money flow had no test coverage at all, despite deciding
 * matters.collection_status and feeding the incentive engine's base amount.
 */
class FeeCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_matter_with_no_fees_reports_no_fees(): void
    {
        $matter = Matter::factory()->create();

        $matter->updateCollectionStatus();

        $this->assertEquals(MatterCollectionStatus::NO_FEES, $matter->fresh()->collection_status);
    }

    public function test_collection_status_moves_unpaid_to_partial_to_paid(): void
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create(['amount' => 1000]);

        $this->assertEquals(MatterCollectionStatus::UNPAID, $matter->fresh()->collection_status);

        Allocation::factory()->for($fee)->create(['amount' => 400]);
        $this->assertEquals(MatterCollectionStatus::PARTIAL, $matter->fresh()->collection_status);

        Allocation::factory()->for($fee)->create(['amount' => 600]);
        $this->assertEquals(MatterCollectionStatus::PAID, $matter->fresh()->collection_status);
    }

    public function test_fee_status_tracks_its_allocations(): void
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create(['amount' => 1000]);

        $fee->updateStatus();
        $this->assertEquals(FeeStatus::UNPAID, $fee->fresh()->status);

        Allocation::factory()->for($fee)->create(['amount' => 250]);
        $this->assertEquals(FeeStatus::PARTIAL, $fee->fresh()->status);

        Allocation::factory()->for($fee)->create(['amount' => 750]);
        $this->assertEquals(FeeStatus::PAID, $fee->fresh()->status);

        Allocation::factory()->for($fee)->create(['amount' => 100]);
        $this->assertEquals(FeeStatus::OVERPAID, $fee->fresh()->status);
    }

    public function test_deduction_type_fees_are_stored_negative(): void
    {
        // Fee::saving() normalises the sign. Production still contains 20 office
        // share rows stored positive, which predate that hook — this pins the
        // behaviour so new rows can't drift the same way.
        $matter = Matter::factory()->create();

        $fee = Fee::factory()->for($matter)->create([
            'type' => FeeType::OFFICE_SHARE,
            'amount' => 750,
        ]);

        $this->assertEquals(-750.0, (float) $fee->fresh()->amount);
    }

    public function test_a_court_penalty_fee_flags_the_matter(): void
    {
        $matter = Matter::factory()->create(['has_court_penalty' => false]);

        Fee::factory()->for($matter)->create([
            'type' => FeeType::COURT_PENALITY,
            'amount' => 500,
        ]);

        $this->assertTrue((bool) $matter->fresh()->has_court_penalty);
    }

    public function test_deleting_a_fee_removes_its_allocations(): void
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create(['amount' => 1000]);
        Allocation::factory()->for($fee)->create(['amount' => 500]);

        $fee->delete();

        $this->assertSame(0, Allocation::where('fee_id', $fee->id)->count());
    }
}
