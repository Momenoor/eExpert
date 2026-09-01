<?php

namespace Tests\Feature;

use App\Enums\FeeType;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Models\IncentiveLineDeduction;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\MatterTypeIncentiveConfig;
use App\Models\Party;
use App\Models\Type;
use App\Services\IncentiveCalculatorService;
use App\Services\IncentiveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncentiveImportAndCalculateTest extends TestCase
{
    use RefreshDatabase;

    public function test_qualifying_matters_are_scoped_to_the_selected_assistant(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);

        $assistantA = Party::create(['name' => 'Assistant A', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $assistantB = Party::create(['name' => 'Assistant B', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);

        $matterA = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        $matterB = Matter::create(['number' => '2', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matterA->id, 'party_id' => $assistantA->id, 'role' => 'expert', 'type' => 'assistant']);
        MatterParty::create(['matter_id' => $matterB->id, 'party_id' => $assistantB->id, 'role' => 'expert', 'type' => 'assistant']);
        Fee::create(['matter_id' => $matterA->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);
        Fee::create(['matter_id' => $matterB->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $service = new IncentiveService;
        $start = Carbon::parse('2026-06-01');
        $end = Carbon::parse('2026-06-30');

        // Filtering by assistant A must return only matterA, never matterB.
        $matters = $service->getQualifyingMatters($start, $end, ['assistant_ids' => [$assistantA->id]]);

        $this->assertCount(1, $matters);
        $this->assertEquals($matterA->id, $matters->first()->id);
    }

    public function test_fees_registered_date_trigger_uses_the_fees_own_date_not_the_collection_date(): void
    {
        // Regression: the "fees_registered_date" trigger type must scope a
        // matter to the period by the fee's own registration date (fees.date),
        // NOT by when it was actually collected (allocations.date) — a fee
        // registered in the period but not yet (or only partially) collected
        // must still qualify, and the incentive later uses the full
        // registered amount regardless of collection.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Registered Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'fees_registered_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        // Fee registered inside the period, collected (allocated) OUTSIDE it —
        // must still qualify because registration date, not collection date, governs.
        $fee = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        Allocation::create(['fee_id' => $fee->id, 'matter_id' => $matter->id, 'amount' => 400, 'date' => '2026-08-01']);

        $service = new IncentiveService;
        $matters = $service->getQualifyingMatters(
            Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), ['assistant_ids' => [$assistant->id]]
        );
        $this->assertCount(1, $matters);

        $calc = IncentiveCalculation::create([
            'name' => 'Registered Date Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calc, [$matter->id]);
        app(IncentiveCalculatorService::class)->calculate($calc);

        // The full registered fee amount (1000) is used, not the 400
        // collected so far: 1000 * 10% = 100.
        $line = IncentiveLine::where('incentive_calculation_id', $calc->id)->first();
        $this->assertEquals(1000.0, (float) $line->fee_amount_excl_vat);
        $this->assertEquals(100.0, (float) $line->net_amount);
    }

    public function test_finished_matter_with_no_fees_still_imports_and_counts_toward_the_monthly_quota(): void
    {
        // Regression: a matter finished (final_report_at) within the period
        // but with NO fees at all (e.g. pro bono, or the fee not yet
        // registered) must still be importable and still count toward the
        // assistant's monthly achievement quota — even though it
        // contributes nothing monetarily.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Tiered', 'calculation_type' => 'tiered', 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Tiered Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $service = new IncentiveService;
        $matters = $service->getQualifyingMatters(
            Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), ['assistant_ids' => [$assistant->id]]
        );
        $this->assertCount(1, $matters);

        $calc = IncentiveCalculation::create([
            'name' => 'No Fee Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calc, [$matter->id]);

        $line = IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matter->id)->first();
        $this->assertNotNull($line);
        $this->assertNull($line->fee_id);

        app(IncentiveCalculatorService::class)->calculate($calc);

        $extra = IncentiveAssistantExtra::where('incentive_calculation_id', $calc->id)->where('party_id', $assistant->id)->first();
        $this->assertEquals(1, $extra->completed_matter_count);

        // Re-importing must not create a second fee-less line for the same matter.
        $service->importSelectedMatters($calc, [$matter->id]);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matter->id)->count());
    }

    public function test_deduction_type_fee_never_becomes_its_own_incentive_line(): void
    {
        // Regression: a COURT_PENALITY (or any deduction-type) fee must never
        // be imported as its own IncentiveLine — only the matter's genuine
        // revenue fee should get a line; the penalty instead nets against it
        // (see IncentiveCalculatorTest for the net-amount math itself).
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);

        $revenueFee = Fee::create(['matter_id' => $matter->id, 'type' => FeeType::EXPERT_FEE, 'amount' => 5000, 'date' => '2026-06-10', 'status' => 'unpaid']);
        Fee::create(['matter_id' => $matter->id, 'type' => FeeType::COURT_PENALITY, 'amount' => 800, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $service = new IncentiveService;
        $calc = IncentiveCalculation::create([
            'name' => 'No Penalty Line Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calc, [$matter->id]);

        $lines = IncentiveLine::where('incentive_calculation_id', $calc->id)->get();
        $this->assertCount(1, $lines);
        $this->assertEquals($revenueFee->id, $lines->first()->fee_id);
    }

    public function test_matter_with_only_a_deduction_type_fee_still_imports_as_fee_less_quota_line(): void
    {
        // Regression: a matter whose only registered fee is a deduction type
        // (e.g. a court penalty with no expert fee at all) must fall back to
        // the existing fee-less quota-only line, exactly like a matter with
        // no fees at all — it must not simply be skipped.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        Fee::create(['matter_id' => $matter->id, 'type' => FeeType::COURT_PENALITY, 'amount' => 500, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $service = new IncentiveService;
        $calc = IncentiveCalculation::create([
            'name' => 'Penalty Only Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calc, [$matter->id]);

        $line = IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matter->id)->first();
        $this->assertNotNull($line);
        $this->assertNull($line->fee_id);
    }

    public function test_import_creates_lines_only_for_selected_matters_and_recalculate_never_reimports(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);

        $matterA = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        $matterB = Matter::create(['number' => '2', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matterA->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        MatterParty::create(['matter_id' => $matterB->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        Fee::create(['matter_id' => $matterA->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);
        Fee::create(['matter_id' => $matterB->id, 'amount' => 2000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Import Test Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);

        $service = new IncentiveService;

        // Only import matterA — matterB must be left untouched.
        $service->importSelectedMatters($calc, [$matterA->id]);

        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->exists());
        $this->assertFalse(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterB->id)->exists());

        // "Run Calculation" must only recompute existing lines — never add or
        // remove rows, and never touch matterB just because it also qualifies.
        $calculator = app(IncentiveCalculatorService::class);
        $calculator->calculate($calc);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        // Running it again (e.g. after editing an override) must stay idempotent.
        $calculator->calculate($calc);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        // Importing matterB afterwards must add exactly one more line, leaving
        // matterA's already-calculated line alone.
        $lineABefore = IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->first();

        $service->importSelectedMatters($calc, [$matterB->id]);
        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        $calculator->calculate($calc);
        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertEquals(100.0, (float) IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->value('net_amount'));
        $this->assertEquals(200.0, (float) IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterB->id)->value('net_amount'));
    }

    public function test_reimporting_an_already_imported_matter_does_not_create_a_duplicate_line(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Duplicate Import Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);

        $service = new IncentiveService;
        $service->importSelectedMatters($calc, [$matter->id]);
        $service->importSelectedMatters($calc, [$matter->id]);

        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
    }

    public function test_importing_with_all_assistant_ids_picks_up_every_qualifying_matter(): void
    {
        // "Import All Qualifying Matters" passes every assistant/expert party
        // ID at once instead of a single filtered selection.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);

        $assistantA = Party::create(['name' => 'Assistant A', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $assistantB = Party::create(['name' => 'Assistant B', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);

        $matterA = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        $matterB = Matter::create(['number' => '2', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matterA->id, 'party_id' => $assistantA->id, 'role' => 'expert', 'type' => 'assistant']);
        MatterParty::create(['matter_id' => $matterB->id, 'party_id' => $assistantB->id, 'role' => 'expert', 'type' => 'assistant']);
        Fee::create(['matter_id' => $matterA->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);
        Fee::create(['matter_id' => $matterB->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Import All Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);

        $service = new IncentiveService;
        $matters = $service->getQualifyingMatters(
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30'),
            ['assistant_ids' => [$assistantA->id, $assistantB->id]]
        );
        $service->importSelectedMatters($calc, $matters->pluck('id')->toArray());

        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->exists());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterB->id)->exists());
    }

    public function test_current_status_matter_imports_one_line_per_fee_registered_in_the_period(): void
    {
        // A still-ongoing matter (allow_current_status_import, no final
        // report yet) with TWO fees each REGISTERED within the same period —
        // and neither one collected/allocated at all yet — must still import
        // as TWO separate lines: registration date governs, not collection.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create([
            'name' => 'Bankruptcy', 'incentive_config_id' => $config->id,
            'incentive_trigger_type' => 'final_report_date', 'allow_current_status_import' => true,
        ]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => null]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $feeOne = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-01', 'status' => 'unpaid']);
        $feeTwo = Fee::create(['matter_id' => $matter->id, 'amount' => 2000, 'date' => '2026-06-05', 'status' => 'unpaid']);

        $service = new IncentiveService;
        $start = Carbon::parse('2026-06-01');
        $end = Carbon::parse('2026-06-30');

        $matters = $service->getQualifyingMatters($start, $end, ['assistant_ids' => [$assistant->id]]);
        $this->assertCount(1, $matters);

        $calc = IncentiveCalculation::create([
            'name' => 'Current Status Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calc, [$matter->id]);

        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $feeOne->id)->exists());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $feeTwo->id)->exists());
    }

    public function test_current_status_matter_is_reimportable_in_a_later_period_for_a_new_uncalculated_fee(): void
    {
        // Once a fee has an incentive calculated on it, it must never be
        // reimportable in a later period. But the SAME still-ongoing matter
        // must remain importable later once a NEW fee (not yet incentivized)
        // is registered — locking is per-fee, not per-matter, and governed
        // by registration date, not collection date.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create([
            'name' => 'Bankruptcy', 'incentive_config_id' => $config->id,
            'incentive_trigger_type' => 'final_report_date', 'allow_current_status_import' => true,
        ]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => null]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $feeOne = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-01', 'status' => 'unpaid']);

        $service = new IncentiveService;
        $calcOne = IncentiveCalculation::create([
            'name' => 'Period 1 Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calcOne, [$matter->id]);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calcOne->id)->count());

        // No new fee yet — must NOT qualify again for the next period.
        $mattersBeforeNewFee = $service->getQualifyingMatters(
            Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'), ['assistant_ids' => [$assistant->id]]
        );
        $this->assertCount(0, $mattersBeforeNewFee);

        // A new fee registered in the next period is added (still uncollected).
        $feeTwo = Fee::create(['matter_id' => $matter->id, 'amount' => 1500, 'date' => '2026-07-05', 'status' => 'unpaid']);

        $mattersAfterNewFee = $service->getQualifyingMatters(
            Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'), ['assistant_ids' => [$assistant->id]]
        );
        $this->assertCount(1, $mattersAfterNewFee);

        $calcTwo = IncentiveCalculation::create([
            'name' => 'Period 2 Calc', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'draft',
        ]);
        $service->importSelectedMatters($calcTwo, [$matter->id]);

        // Only the NEW fee is imported into period 2 — the already-incentivized
        // fee from period 1 is left untouched.
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calcTwo->id)->count());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calcTwo->id)->where('fee_id', $feeTwo->id)->exists());
        $this->assertFalse(IncentiveLine::where('incentive_calculation_id', $calcTwo->id)->where('fee_id', $feeOne->id)->exists());
    }

    public function test_deleting_all_lines_cascades_to_deductions_and_assistant_lines_and_clears_extras(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create([
            'number' => '1', 'year' => '2026', 'type_id' => $type->id,
            'review_count' => 1, 'has_substantive_changes' => true,
        ]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Delete All Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);

        (new IncentiveService)->importSelectedMatters($calc, [$matter->id]);
        app(IncentiveCalculatorService::class)->calculate($calc);

        // Sanity check: the calc has a line, a deduction (review_count=1 with
        // substantive changes triggers one), and an assistant line/extra.
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertGreaterThan(0, IncentiveLineDeduction::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $calc->id)
        )->count());
        $this->assertEquals(1, IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $calc->id)
        )->count());
        $this->assertEquals(1, IncentiveAssistantExtra::where('incentive_calculation_id', $calc->id)->count());

        // "Delete All Lines" action's logic:
        IncentiveLine::where('incentive_calculation_id', $calc->id)->delete();
        IncentiveAssistantExtra::where('incentive_calculation_id', $calc->id)->delete();

        $this->assertEquals(0, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertEquals(0, IncentiveLineDeduction::query()->count());
        $this->assertEquals(0, IncentiveAssistantLine::query()->count());
        $this->assertEquals(0, IncentiveAssistantExtra::where('incentive_calculation_id', $calc->id)->count());
    }

    public function test_force_import_matter_bypasses_the_period_and_current_status_scoping(): void
    {
        // Regression: forceImportMatter must import a matter's fee even
        // though its date falls OUTSIDE the calculation's period — the
        // normal getQualifyingMatters()/importSelectedMatters() flow would
        // never surface it, since it's a current-status type scoped to
        // fees registered within the period.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create([
            'name' => 'Bankruptcy', 'incentive_config_id' => $config->id,
            'incentive_trigger_type' => 'final_report_date', 'allow_current_status_import' => true,
        ]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => null]);

        // Fee registered OUTSIDE this calculation's period.
        $fee = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-09-15', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Force Import Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);

        $service = new IncentiveService;
        $imported = $service->forceImportMatter($calc, $matter->id);

        $this->assertEquals(1, $imported);
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $fee->id)->exists());
    }

    public function test_force_import_matter_skips_a_fee_already_imported_elsewhere(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-05-01']);
        $fee = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-05-01', 'status' => 'unpaid']);

        $calcOne = IncentiveCalculation::create([
            'name' => 'Original Calc', 'period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'status' => 'draft',
        ]);
        $calcTwo = IncentiveCalculation::create([
            'name' => 'Second Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);

        $service = new IncentiveService;
        $service->importSelectedMatters($calcOne, [$matter->id]);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calcOne->id)->count());

        // Force-importing the SAME matter into a different calculation must
        // not duplicate the already-imported fee.
        $imported = $service->forceImportMatter($calcTwo, $matter->id);

        $this->assertEquals(0, $imported);
        $this->assertEquals(0, IncentiveLine::where('incentive_calculation_id', $calcTwo->id)->count());
    }

    public function test_recalculate_automatically_picks_up_a_fee_registered_after_the_matter_was_imported(): void
    {
        // Regression: a fee registered on a matter AFTER it was already
        // imported into a calculation (e.g. a late-arriving payment) must be
        // picked up automatically the next time "Run Calculation" executes —
        // not require a manual "Add Specific Matter" force-import.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create([
            'name' => 'Bankruptcy', 'incentive_config_id' => $config->id,
            'incentive_trigger_type' => 'final_report_date', 'allow_current_status_import' => true,
        ]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => null]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $feeOne = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-01', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Late Fee Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service = new IncentiveService;
        $service->importSelectedMatters($calc, [$matter->id]);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        // A second fee is registered on the SAME matter after it was imported.
        $feeTwo = Fee::create(['matter_id' => $matter->id, 'amount' => 500, 'date' => '2026-06-15', 'status' => 'unpaid']);

        // "Run Calculation" must pick it up on its own — no manual re-import.
        app(IncentiveCalculatorService::class)->calculate($calc);

        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $feeOne->id)->exists());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $feeTwo->id)->exists());

        // Idempotent: recalculating again must not duplicate the line.
        app(IncentiveCalculatorService::class)->calculate($calc);
        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
    }

    public function test_recalculate_does_not_sweep_in_a_fee_registered_outside_the_calculations_period(): void
    {
        // Regression: a fee registered on an already-imported matter but
        // dated OUTSIDE this calculation's period must NOT be picked up by
        // recalculation — it belongs to whichever period's own calculation
        // covers its date (or a deliberate "Add Specific Matter" force-import).
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create([
            'name' => 'Bankruptcy', 'incentive_config_id' => $config->id,
            'incentive_trigger_type' => 'final_report_date', 'allow_current_status_import' => true,
        ]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => null]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $feeOne = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-01', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Period Scoped Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service = new IncentiveService;
        $service->importSelectedMatters($calc, [$matter->id]);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        // A fee registered on the SAME matter but dated in the NEXT period.
        $feeTwo = Fee::create(['matter_id' => $matter->id, 'amount' => 500, 'date' => '2026-07-05', 'status' => 'unpaid']);

        app(IncentiveCalculatorService::class)->calculate($calc);

        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertFalse(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $feeTwo->id)->exists());
    }

    public function test_recalculate_picks_up_a_new_fee_for_a_finished_matter_regardless_of_its_date(): void
    {
        // A finished (final_report_date-triggered, non-current-status)
        // matter has no per-fee period scoping on first import — every
        // eligible fee belongs to it regardless of date. Recalculation must
        // apply the SAME rule to a new fee added later, not the
        // current-status matters' period scoping.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $feeOne = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Finished Matter Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service = new IncentiveService;
        $service->importSelectedMatters($calc, [$matter->id]);
        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        // A second fee registered on the matter, dated OUTSIDE this
        // calculation's period — still eligible, since a finished matter's
        // fees aren't scoped to the period.
        $feeTwo = Fee::create(['matter_id' => $matter->id, 'amount' => 300, 'date' => '2026-08-15', 'status' => 'unpaid']);

        app(IncentiveCalculatorService::class)->calculate($calc);

        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('fee_id', $feeTwo->id)->exists());
    }

    public function test_assistant_summary_aggregates_fee_and_base_amount_across_all_of_a_matters_fee_lines(): void
    {
        // Regression: a matter with more than one fee gets one IncentiveLine
        // per fee, but only ONE IncentiveAssistantLine per assistant
        // (attached to the matter's first line). getAssistantSummary()'s
        // per-matter 'fee_amount_excl_vat' and 'base_amount' must reflect
        // the SUM across every fee line, not just the first one — matching
        // 'share_amount'/'total_amount', which were already summed.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create([
            'name' => 'Bankruptcy', 'incentive_config_id' => $config->id,
            'incentive_trigger_type' => 'final_report_date', 'allow_current_status_import' => true,
        ]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => null]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        Fee::create(['matter_id' => $matter->id, 'amount' => 5000, 'date' => '2026-06-01', 'status' => 'unpaid']);
        Fee::create(['matter_id' => $matter->id, 'amount' => 400, 'date' => '2026-06-15', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Multi Fee Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service = new IncentiveService;
        $service->importSelectedMatters($calc, [$matter->id]);
        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        $calculator = app(IncentiveCalculatorService::class);
        $calculator->calculate($calc);

        $summary = $calculator->getAssistantSummary($calc);
        $matterSummary = $summary->first()['matters']->first();

        $this->assertEquals(5400.0, (float) $matterSummary['fee_amount_excl_vat']); // 5000 + 400
        $this->assertEquals(540.0, (float) $matterSummary['base_amount']); // 5400 * 10%
        $this->assertEquals(540.0, (float) $matterSummary['total_amount']); // share_amount already summed correctly

        // The assistant-level 'fees_amount' (used in the print report's
        // upper Assistant Summary table) must also reflect the full 5400,
        // not just the matter's first fee.
        $this->assertEquals(5400.0, (float) $summary->first()['fees_amount']);
    }

    public function test_removing_one_matter_leaves_other_matters_and_their_totals_intact(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);

        $matterA = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        $matterB = Matter::create(['number' => '2', 'year' => '2026', 'type_id' => $type->id, 'final_report_at' => '2026-06-10']);
        MatterParty::create(['matter_id' => $matterA->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        MatterParty::create(['matter_id' => $matterB->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        Fee::create(['matter_id' => $matterA->id, 'amount' => 1000, 'date' => '2026-06-10', 'status' => 'unpaid']);
        Fee::create(['matter_id' => $matterB->id, 'amount' => 2000, 'date' => '2026-06-10', 'status' => 'unpaid']);

        $calc = IncentiveCalculation::create([
            'name' => 'Remove Matter Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $service = new IncentiveService;
        $service->importSelectedMatters($calc, [$matterA->id, $matterB->id]);

        $calculator = app(IncentiveCalculatorService::class);
        $calculator->calculate($calc);
        $this->assertEquals(2, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());

        // Simulate the "Remove Matter" action: delete matter A's lines, then recalculate.
        IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->delete();
        $calculator->calculate($calc);

        $this->assertEquals(1, IncentiveLine::where('incentive_calculation_id', $calc->id)->count());
        $this->assertFalse(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->exists());
        $remainingLine = IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterB->id)->first();
        $this->assertEquals(200.0, (float) $remainingLine->net_amount); // 2000 * 10%, untouched by the removal
    }
}
