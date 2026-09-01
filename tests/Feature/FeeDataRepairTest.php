<?php

namespace Tests\Feature;

use App\Enums\FeeStatus;
use App\Enums\FeeType;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\Setting;
use App\Models\User;
use App\Services\FeeDataRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * These repairs rewrite live financial records, so each one is pinned: what it
 * changes, what it leaves alone, and that running it twice is a no-op.
 */
class FeeDataRepairTest extends TestCase
{
    use RefreshDatabase;

    private FeeDataRepairService $repairs;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearCache();
        $this->actingAs(User::factory()->create());
        $this->repairs = app(FeeDataRepairService::class);
    }

    private function assistantOn(Matter $matter, string $type = 'certified'): Party
    {
        $party = Party::factory()->certifiedExpert()->create();

        MatterParty::create([
            'matter_id' => $matter->id,
            'party_id' => $party->id,
            'role' => 'expert',
            'type' => $type,
        ]);

        return $party;
    }

    /**
     * Legacy shape: a negative fee whose payment is still positive. Both hooks
     * now prevent this, so it has to be written straight to the table.
     */
    private function misalignedPair(float $magnitude = 1050): array
    {
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => $magnitude]);
        $allocation = Allocation::factory()->for($fee)->create(['amount' => $magnitude]);

        DB::table('allocations')->where('id', $allocation->id)->update(['amount' => $magnitude]);

        return [$fee, $allocation];
    }

    // ── 0. Allocation signs ──────────────────────────────────────────────────

    public function test_it_flips_a_payment_that_runs_against_its_fee(): void
    {
        [$fee, $allocation] = $this->misalignedPair();

        $preview = $this->repairs->previewAllocationSignAlignment();
        $this->assertSame(1, $preview['rows']);
        $this->assertEquals(1050.0, $preview['value']);
        $this->assertSame(1, $preview['fees']);

        $this->repairs->alignAllocationSigns();

        $this->assertEquals(-1050.0, (float) $allocation->fresh()->amount);
        $this->assertEquals(-1050.0, (float) $fee->fresh()->amount, 'the fee itself must not move');
        $this->assertEquals(FeeStatus::PAID, $fee->fresh()->status);
    }

    public function test_aligning_signs_nets_the_matter_to_zero(): void
    {
        [$fee] = $this->misalignedPair();
        $matter = $fee->matter;

        // Before: the matter is billed -1050 but has received +1050, so it reads
        // as over-collected by twice the fee — the aging-report false positive.
        $this->repairs->alignAllocationSigns();

        $owed = (float) Fee::where('matter_id', $matter->id)->sum('amount');
        $received = (float) Allocation::where('matter_id', $matter->id)->sum('amount');

        $this->assertEqualsWithDelta(0.0, $owed - $received, 0.005);
    }

    public function test_aligning_signs_is_idempotent(): void
    {
        $this->misalignedPair();

        $this->repairs->alignAllocationSigns();
        $second = $this->repairs->alignAllocationSigns();

        $this->assertSame(0, $second['rows']);
    }

    public function test_aligning_signs_leaves_correctly_signed_payments_alone(): void
    {
        $revenue = Fee::factory()->create(['type' => FeeType::EXPERT_FEE, 'amount' => 3000]);
        Allocation::factory()->for($revenue)->create(['amount' => 3000]);

        $deduction = Fee::factory()->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 750]);
        Allocation::factory()->for($deduction)->create(['amount' => 750]);

        $result = $this->repairs->alignAllocationSigns();

        $this->assertSame(0, $result['rows']);
        $this->assertEquals(3000.0, (float) Allocation::where('fee_id', $revenue->id)->sum('amount'));
        // The creating hook already matched the fee's direction.
        $this->assertEquals(-750.0, (float) Allocation::where('fee_id', $deduction->id)->sum('amount'));
    }

    public function test_an_unrelated_save_does_not_negate_a_legacy_positive_deduction_fee(): void
    {
        // The bug this guards: Fee::saving() flipped the amount on EVERY save,
        // so merely recalculating a status turned a legacy +1050 office share
        // into -1050 while its +1050 payment stayed put.
        $fee = Fee::factory()->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 1050]);
        DB::table('fees')->where('id', $fee->id)->update(['amount' => 1050]);

        $fee = $fee->fresh();
        $fee->description = 'touched by something unrelated';
        $fee->save();

        $this->assertEquals(1050.0, (float) $fee->fresh()->amount);
    }

    public function test_setting_the_amount_still_normalises_a_deduction_fee(): void
    {
        $fee = Fee::factory()->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 1050]);
        $this->assertEquals(-1050.0, (float) $fee->fresh()->amount);

        $fee->update(['amount' => 500]);

        $this->assertEquals(-500.0, (float) $fee->fresh()->amount);
    }

    // ── 1. Duplicate allocations ─────────────────────────────────────────────

    public function test_it_removes_duplicate_allocations_keeping_the_earliest(): void
    {
        $fee = Fee::factory()->create(['amount' => 1000]);

        $keep = Allocation::factory()->for($fee)->create(['amount' => 500, 'date' => '2026-06-01']);
        $dupe = Allocation::factory()->for($fee)->create(['amount' => 500, 'date' => '2026-06-01']);
        $other = Allocation::factory()->for($fee)->create(['amount' => 500, 'date' => '2026-06-02']);

        $preview = $this->repairs->previewDuplicateAllocations();
        $this->assertSame(1, $preview['rows']);
        $this->assertEquals(500.0, $preview['value']);

        $this->repairs->removeDuplicateAllocations();

        $this->assertNotNull($keep->fresh(), 'the earliest row of the set must survive');
        $this->assertNull($dupe->fresh(), 'the duplicate must be deleted');
        $this->assertNotNull($other->fresh(), 'a same-amount payment on a different date is not a duplicate');
    }

    public function test_removing_duplicates_is_idempotent(): void
    {
        $fee = Fee::factory()->create(['amount' => 1000]);
        Allocation::factory()->for($fee)->create(['amount' => 500, 'date' => '2026-06-01']);
        Allocation::factory()->for($fee)->create(['amount' => 500, 'date' => '2026-06-01']);

        $this->repairs->removeDuplicateAllocations();
        $second = $this->repairs->removeDuplicateAllocations();

        $this->assertSame(0, $second['rows']);
        $this->assertSame(1, Allocation::where('fee_id', $fee->id)->count());
    }

    // ── 2. Over-collection ───────────────────────────────────────────────────

    public function test_it_trims_a_partial_over_collection_without_deleting_the_payment(): void
    {
        $fee = Fee::factory()->create(['amount' => 1000]);
        Allocation::factory()->for($fee)->create(['amount' => 1200, 'date' => '2026-06-01']);

        $this->repairs->trimOverCollection();

        $this->assertEquals(1000.0, (float) Allocation::where('fee_id', $fee->id)->sum('amount'));
        $this->assertSame(1, Allocation::where('fee_id', $fee->id)->count());
        $this->assertEquals(FeeStatus::PAID, $fee->fresh()->status);
    }

    public function test_it_removes_whole_surplus_payments_newest_first(): void
    {
        $fee = Fee::factory()->create(['amount' => 1000]);
        $original = Allocation::factory()->for($fee)->create(['amount' => 1000, 'date' => '2026-06-01']);
        $surplus = Allocation::factory()->for($fee)->create(['amount' => 400, 'date' => '2026-07-01']);

        $this->repairs->trimOverCollection();

        $this->assertNotNull($original->fresh(), 'the original payment must be kept');
        $this->assertNull($surplus->fresh(), 'the later surplus payment is the one removed');
        $this->assertEquals(1000.0, (float) Allocation::where('fee_id', $fee->id)->sum('amount'));
    }

    public function test_trimming_leaves_correctly_collected_fees_alone(): void
    {
        $fee = Fee::factory()->create(['amount' => 1000]);
        Allocation::factory()->for($fee)->create(['amount' => 600]);

        $result = $this->repairs->trimOverCollection();

        $this->assertSame(0, $result['fees']);
        $this->assertEquals(600.0, (float) Allocation::where('fee_id', $fee->id)->sum('amount'));
    }

    public function test_trimming_respects_the_sign_of_a_deduction_fee(): void
    {
        // Office share is stored negative and paid down negatively; an
        // over-collection here means the magnitude is too large, and the trim
        // must not flip it positive.
        $matter = Matter::factory()->create();
        $fee = Fee::factory()->for($matter)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 750]);
        Allocation::factory()->for($fee)->create(['amount' => -900]);

        $this->repairs->trimOverCollection();

        $this->assertEquals(-750.0, (float) Allocation::where('fee_id', $fee->id)->sum('amount'));
    }

    // ── 3. Settling non-owner matters ────────────────────────────────────────

    public function test_it_settles_an_under_collected_fee_on_a_non_owner_matter(): void
    {
        $owner = Party::factory()->certifiedExpert()->create();
        Setting::set('office_owner_party_id', $owner->id, 'incentive');

        $ownerMatter = Matter::factory()->create();
        MatterParty::create(['matter_id' => $ownerMatter->id, 'party_id' => $owner->id, 'role' => 'expert', 'type' => 'certified']);
        $ownerFee = Fee::factory()->for($ownerMatter)->create(['amount' => 5000]);

        $otherMatter = Matter::factory()->create();
        $this->assistantOn($otherMatter);
        $otherFee = Fee::factory()->for($otherMatter)->create(['amount' => 2000]);
        Allocation::factory()->for($otherFee)->create(['amount' => 500]);

        $preview = $this->repairs->previewNonOwnerSettlement();
        $this->assertSame(1, $preview['fees'], 'only the non-owner matter is in scope');
        $this->assertEquals(1500.0, $preview['shortfall']);

        $this->repairs->settleNonOwnerMatters();

        $this->assertEquals(2000.0, (float) Allocation::where('fee_id', $otherFee->id)->sum('amount'));
        $this->assertEquals(FeeStatus::PAID, $otherFee->fresh()->status);

        // The owner's own matter must be untouched.
        $this->assertSame(0, Allocation::where('fee_id', $ownerFee->id)->count());
    }

    public function test_settlement_closes_every_fee_type_including_deductions(): void
    {
        $owner = Party::factory()->certifiedExpert()->create();
        Setting::set('office_owner_party_id', $owner->id, 'incentive');

        $matter = Matter::factory()->create();
        $this->assistantOn($matter);

        $expertFee = Fee::factory()->for($matter)->create(['type' => FeeType::EXPERT_FEE, 'amount' => 3000]);
        $officeShare = Fee::factory()->for($matter)->create(['type' => FeeType::OFFICE_SHARE, 'amount' => 750]);
        $vat = Fee::factory()->for($matter)->create(['type' => FeeType::VAT, 'amount' => 150]);

        $this->repairs->settleNonOwnerMatters();

        $this->assertEquals(3000.0, (float) Allocation::where('fee_id', $expertFee->id)->sum('amount'));
        // Stored negative, so it settles negatively.
        $this->assertEquals(-750.0, (float) Allocation::where('fee_id', $officeShare->id)->sum('amount'));
        $this->assertEquals(150.0, (float) Allocation::where('fee_id', $vat->id)->sum('amount'));

        foreach ([$expertFee, $officeShare, $vat] as $fee) {
            $this->assertEquals(FeeStatus::PAID, $fee->fresh()->status);
        }
    }

    public function test_settlement_is_idempotent(): void
    {
        $owner = Party::factory()->certifiedExpert()->create();
        Setting::set('office_owner_party_id', $owner->id, 'incentive');

        $matter = Matter::factory()->create();
        $this->assistantOn($matter);
        $fee = Fee::factory()->for($matter)->create(['amount' => 1000]);

        $this->repairs->settleNonOwnerMatters();
        $second = $this->repairs->settleNonOwnerMatters();

        $this->assertSame(0, $second['fees']);
        $this->assertEquals(1000.0, (float) Allocation::where('fee_id', $fee->id)->sum('amount'));
        $this->assertSame(1, Allocation::where('fee_id', $fee->id)->count());
    }

    public function test_settlement_does_not_write_negative_payments_for_over_collection(): void
    {
        // An over-collected fee is step 2's job. Settlement must skip it rather
        // than record a negative "payment" to claw the money back.
        $owner = Party::factory()->certifiedExpert()->create();
        Setting::set('office_owner_party_id', $owner->id, 'incentive');

        $matter = Matter::factory()->create();
        $this->assistantOn($matter);
        $fee = Fee::factory()->for($matter)->create(['amount' => 1000]);
        Allocation::factory()->for($fee)->create(['amount' => 1400]);

        $result = $this->repairs->settleNonOwnerMatters();

        $this->assertSame(0, $result['fees']);
        $this->assertSame(1, $result['skipped_over']);
        $this->assertEquals(1400.0, (float) Allocation::where('fee_id', $fee->id)->sum('amount'));
    }

    // ── The three together ───────────────────────────────────────────────────

    public function test_the_full_sequence_leaves_every_fee_settled_exactly(): void
    {
        $owner = Party::factory()->certifiedExpert()->create();
        Setting::set('office_owner_party_id', $owner->id, 'incentive');

        $matter = Matter::factory()->create();
        $this->assistantOn($matter);

        // Duplicated payment, which also causes the over-collection.
        $feeA = Fee::factory()->for($matter)->create(['amount' => 1000]);
        Allocation::factory()->for($feeA)->create(['amount' => 1000, 'date' => '2026-06-01']);
        Allocation::factory()->for($feeA)->create(['amount' => 1000, 'date' => '2026-06-01']);

        // Under-collected.
        $feeB = Fee::factory()->for($matter)->create(['amount' => 2000]);
        Allocation::factory()->for($feeB)->create(['amount' => 250]);

        $this->repairs->removeDuplicateAllocations();
        $this->repairs->trimOverCollection();
        $this->repairs->settleNonOwnerMatters();

        $this->assertEquals(1000.0, (float) Allocation::where('fee_id', $feeA->id)->sum('amount'));
        $this->assertEquals(2000.0, (float) Allocation::where('fee_id', $feeB->id)->sum('amount'));
        $this->assertEquals(FeeStatus::PAID, $feeA->fresh()->status);
        $this->assertEquals(FeeStatus::PAID, $feeB->fresh()->status);
    }
}
