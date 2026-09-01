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

    public function test_a_revenue_fee_holding_the_gross_on_a_commission_matter_is_paid_not_overpaid(): void
    {
        // The commission pattern: the client's gross payment lands on the
        // revenue fee while the office-share line carries the offsetting
        // negative. ~405 production fees look like this. Before the offset
        // allowance they all read as overpaid the moment updateStatus() ran.
        $matter = Matter::factory()->create();

        $expertFee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 3000,
        ]);
        Fee::factory()->for($matter)->create([
            'type' => FeeType::OFFICE_SHARE,
            'amount' => 750,
        ]);

        Allocation::factory()->for($expertFee)->create(['amount' => 3750]);

        $expertFee->refresh()->updateStatus();

        $this->assertEquals(FeeStatus::PAID, $expertFee->fresh()->status);
    }

    public function test_a_fully_settled_deduction_fee_is_paid_not_unpaid(): void
    {
        // Deduction fees are stored negative and paid down negatively, so the
        // ladder has to compare magnitudes — a -750 office share settled by a
        // -750 allocation previously reported UNPAID.
        $matter = Matter::factory()->create();

        $officeShare = Fee::factory()->for($matter)->create([
            'type' => FeeType::OFFICE_SHARE,
            'amount' => 750,
        ]);

        Allocation::factory()->for($officeShare)->create(['amount' => -750]);

        $officeShare->refresh()->updateStatus();

        $this->assertEquals(FeeStatus::PAID, $officeShare->fresh()->status);
    }

    public function test_a_genuine_overpayment_beyond_the_offset_is_still_overpaid(): void
    {
        // The allowance must not swallow real over-collection: 3,000 fee with a
        // 750 office share tolerates up to 3,750, so 4,500 is still overpaid.
        $matter = Matter::factory()->create();

        $expertFee = Fee::factory()->for($matter)->create([
            'type' => FeeType::EXPERT_FEE,
            'amount' => 3000,
        ]);
        Fee::factory()->for($matter)->create([
            'type' => FeeType::OFFICE_SHARE,
            'amount' => 750,
        ]);

        Allocation::factory()->for($expertFee)->create(['amount' => 4500]);

        $expertFee->refresh()->updateStatus();

        $this->assertEquals(FeeStatus::OVERPAID, $expertFee->fresh()->status);
    }

    public function test_an_allocation_against_a_deduction_fee_is_stored_negative(): void
    {
        $matter = Matter::factory()->create();
        $officeShare = Fee::factory()->for($matter)->create([
            'type' => FeeType::OFFICE_SHARE,
            'amount' => 750,
        ]);

        // Recorded positive by a caller that does not flip the sign itself.
        $allocation = Allocation::factory()->for($officeShare)->create(['amount' => 750]);

        $this->assertEquals(-750.0, (float) $allocation->fresh()->amount);
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
