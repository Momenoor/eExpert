<?php

namespace Tests\Feature;

use App\Enums\MatterCommissiong;
use App\Enums\MatterDifficulty;
use App\Filament\Widgets\IncentiveSummaryTableWidget;
use App\Models\Court;
use App\Models\Fee;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\MatterTypeIncentiveConfig;
use App\Models\Party;
use App\Models\Type;
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncentiveSummaryTableWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeCalculationWithOneMatter(): array
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $calc = IncentiveCalculation::create([
            'name' => 'Widget Test Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $fee = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        $line = IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matter->id, 'fee_id' => $fee->id]);

        app(IncentiveCalculatorService::class)->calculate($calc);

        return [$calc, $matter, $assistant, $line];
    }

    public function test_widget_renders_the_calculation_lines(): void
    {
        [$calc] = $this->makeCalculationWithOneMatter();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->assertSuccessful()
            ->assertSee('100.00'); // 1000 fee * 10% — visible in the default (non-toggled) Share/Total columns
    }

    public function test_edit_percentage_action_sets_override_for_that_assistant_only(): void
    {
        [$calc, $matter, $assistant] = $this->makeCalculationWithOneMatter();
        $this->actingAs(User::factory()->create());

        $assistantLine = IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $calc->id)
        )->where('party_id', $assistant->id)->first();

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->callTableAction('editPercentage', $assistantLine, data: ['percentage_override' => 15])
            ->assertHasNoTableActionErrors();

        // Regression: the action must reapply the calculation immediately —
        // share_amount/total_amount are cached columns that previously
        // stayed stale until a separate manual "Recalculate" click. Note the
        // recalculation deletes and recreates assistant lines, so the
        // original row's id no longer exists — re-fetch by party instead.
        $assistantLine = IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $calc->id)
        )->where('party_id', $assistant->id)->first();
        $this->assertEquals(15.0, (float) $assistantLine->percentage_override);
        $this->assertEquals(150.0, (float) $assistantLine->share_amount); // 1000 fee * 15%

        // The matter's own auto-computed percentage is untouched by this
        // assistant-specific override.
        $line = IncentiveLine::where('incentive_calculation_id', $calc->id)
            ->where('matter_id', $matter->id)
            ->first();
        $this->assertEquals(10.0, (float) $line->effective_percentage);
    }

    public function test_add_deduction_action_updates_the_assistant_extra_record(): void
    {
        [$calc, , $assistant] = $this->makeCalculationWithOneMatter();
        $this->actingAs(User::factory()->create());

        $assistantLine = IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $calc->id)
        )->where('party_id', $assistant->id)->first();

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->callTableAction('addDeduction', $assistantLine, data: [
                'fixed_deduction' => 30,
                'fixed_deduction_reason' => 'Advance recovery',
            ])
            ->assertHasNoTableActionErrors();

        $extra = IncentiveAssistantExtra::where('incentive_calculation_id', $calc->id)
            ->where('party_id', $assistant->id)
            ->first();

        $this->assertEquals(30.0, (float) $extra->fixed_deduction);
        $this->assertEquals('Advance recovery', $extra->fixed_deduction_reason);
    }

    public function test_widget_shows_court_type_commissioning_and_difficulty_from_the_matter(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Custody Case', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $court = Court::create(['name' => 'Fujairah Court']);
        $matter = Matter::create([
            'number' => '1', 'year' => '2026', 'type_id' => $type->id, 'court_id' => $court->id,
            'difficulty' => MatterDifficulty::HARD, 'commissioning' => MatterCommissiong::COMMITTEE,
        ]);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $calc = IncentiveCalculation::create([
            'name' => 'Badges Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $fee = Fee::create(['matter_id' => $matter->id, 'amount' => 1000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matter->id, 'fee_id' => $fee->id]);
        app(IncentiveCalculatorService::class)->calculate($calc);

        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->assertSuccessful()
            ->assertSee('Fujairah Court')
            ->assertSee('Custody Case')
            ->assertSee(MatterCommissiong::COMMITTEE->getLabel())
            ->assertSee(MatterDifficulty::HARD->getLabel());
    }

    public function test_widget_shows_each_assistants_own_percentage_share_next_to_the_matter_rate(): void
    {
        // Regression: the "Rate %" column shows the MATTER's own overall
        // rate (10%), which is the same on every assistant row sharing that
        // matter — but when co-assistants split it unevenly, each one's
        // actual cut (5% here) must also be visible, or the 10% next to a
        // 150 AED share (out of a 3000 fee = 5%) looks like a miscalculation.
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $matter = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id]);
        $assistantOne = Party::create(['name' => 'Assistant One', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        $assistantTwo = Party::create(['name' => 'Assistant Two', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistantOne->id, 'role' => 'expert', 'type' => 'assistant', 'commission_percentage' => 10]);
        MatterParty::create(['matter_id' => $matter->id, 'party_id' => $assistantTwo->id, 'role' => 'expert', 'type' => 'assistant', 'commission_percentage' => 10]);

        $calc = IncentiveCalculation::create([
            'name' => 'Weighted Split Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $fee = Fee::create(['matter_id' => $matter->id, 'amount' => 3000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matter->id, 'fee_id' => $fee->id]);
        app(IncentiveCalculatorService::class)->calculate($calc);

        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->assertSuccessful()
            ->assertSee('150.00') // each assistant's actual share
            ->assertSee('5%'); // each assistant's own percentage cut of the fee
    }

    public function test_delete_matter_row_action_removes_only_that_matters_lines(): void
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $type = Type::create(['name' => 'Fixed Type', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);

        $matterA = Matter::create(['number' => '1', 'year' => '2026', 'type_id' => $type->id]);
        $matterB = Matter::create(['number' => '2', 'year' => '2026', 'type_id' => $type->id]);
        MatterParty::create(['matter_id' => $matterA->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        MatterParty::create(['matter_id' => $matterB->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $calc = IncentiveCalculation::create([
            'name' => 'Delete Row Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $feeA = Fee::create(['matter_id' => $matterA->id, 'amount' => 1000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        $feeB = Fee::create(['matter_id' => $matterB->id, 'amount' => 2000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matterA->id, 'fee_id' => $feeA->id]);
        IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matterB->id, 'fee_id' => $feeB->id]);
        app(IncentiveCalculatorService::class)->calculate($calc);

        $this->actingAs(User::factory()->create());

        $rowToDelete = IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)
        )->first();

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->callTableAction('deleteMatter', $rowToDelete)
            ->assertHasNoTableActionErrors();

        $this->assertFalse(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterA->id)->exists());
        $this->assertTrue(IncentiveLine::where('incentive_calculation_id', $calc->id)->where('matter_id', $matterB->id)->exists());
    }

    private function makeTwoMatterCalculation(): array
    {
        $config = MatterTypeIncentiveConfig::create([
            'name' => 'Fixed 10', 'calculation_type' => 'fixed', 'fixed_percentage' => 10.0, 'assistant_rate' => 100.0,
        ]);
        $typeA = Type::create(['name' => 'Custody Case', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $typeB = Type::create(['name' => 'Commercial Dispute', 'incentive_config_id' => $config->id, 'incentive_trigger_type' => 'final_report_date']);
        $courtA = Court::create(['name' => 'Fujairah Court']);
        $courtB = Court::create(['name' => 'Dubai Court']);

        $matterA = Matter::create([
            'number' => '70', 'year' => '2026', 'type_id' => $typeA->id, 'court_id' => $courtA->id,
            'difficulty' => MatterDifficulty::HARD, 'commissioning' => MatterCommissiong::COMMITTEE,
        ]);
        $matterB = Matter::create([
            'number' => '99', 'year' => '2026', 'type_id' => $typeB->id, 'court_id' => $courtB->id,
            'difficulty' => MatterDifficulty::EASY, 'commissioning' => MatterCommissiong::INDIVIDUAL,
        ]);

        $assistant = Party::create(['name' => 'Assistant', 'role' => ['role' => ['expert'], 'type' => ['assistant']]]);
        MatterParty::create(['matter_id' => $matterA->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);
        MatterParty::create(['matter_id' => $matterB->id, 'party_id' => $assistant->id, 'role' => 'expert', 'type' => 'assistant']);

        $calc = IncentiveCalculation::create([
            'name' => 'Search Calc', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'status' => 'draft',
        ]);
        $feeA = Fee::create(['matter_id' => $matterA->id, 'amount' => 1000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        $feeB = Fee::create(['matter_id' => $matterB->id, 'amount' => 2000, 'date' => '2026-06-15', 'status' => 'unpaid']);
        IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matterA->id, 'fee_id' => $feeA->id]);
        IncentiveLine::create(['incentive_calculation_id' => $calc->id, 'matter_id' => $matterB->id, 'fee_id' => $feeB->id]);
        app(IncentiveCalculatorService::class)->calculate($calc);

        return [$calc, $matterA, $matterB];
    }

    public function test_search_by_reversed_year_number_finds_the_matter(): void
    {
        // Regression: the "2 numeric tokens" branch previously queried
        // year/number directly on IncentiveAssistantLine (which has neither
        // column), throwing an SQL error instead of matching the matter.
        [$calc, $matterA] = $this->makeTwoMatterCalculation();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->searchTable('2026/70')
            ->assertCanSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('matter_id', $matterA->id))->get()
            )
            ->assertCanNotSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calc->id)->where('matter_id', '!=', $matterA->id))->get()
            );
    }

    public function test_search_by_court_name_finds_the_matter(): void
    {
        [$calc, $matterA] = $this->makeTwoMatterCalculation();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->searchTable('Fujairah')
            ->assertCanSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('matter_id', $matterA->id))->get()
            )
            ->assertCanNotSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calc->id)->where('matter_id', '!=', $matterA->id))->get()
            );
    }

    public function test_search_by_type_name_finds_the_matter(): void
    {
        [$calc, , $matterB] = $this->makeTwoMatterCalculation();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->searchTable('Commercial Dispute')
            ->assertCanSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('matter_id', $matterB->id))->get()
            )
            ->assertCanNotSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calc->id)->where('matter_id', '!=', $matterB->id))->get()
            );
    }

    public function test_search_by_difficulty_label_finds_the_matter_regardless_of_locale(): void
    {
        [$calc, $matterA] = $this->makeTwoMatterCalculation();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->searchTable(MatterDifficulty::HARD->getLabel())
            ->assertCanSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('matter_id', $matterA->id))->get()
            )
            ->assertCanNotSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calc->id)->where('matter_id', '!=', $matterA->id))->get()
            );
    }

    public function test_search_by_commissioning_label_finds_the_matter(): void
    {
        [$calc, $matterA] = $this->makeTwoMatterCalculation();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->searchTable(MatterCommissiong::COMMITTEE->getLabel())
            ->assertCanSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('matter_id', $matterA->id))->get()
            )
            ->assertCanNotSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calc->id)->where('matter_id', '!=', $matterA->id))->get()
            );
    }

    public function test_search_by_assistant_name_finds_all_their_matters(): void
    {
        [$calc, $matterA, $matterB] = $this->makeTwoMatterCalculation();
        $this->actingAs(User::factory()->create());

        Livewire::test(IncentiveSummaryTableWidget::class, ['calculationId' => $calc->id])
            ->searchTable('Assistant')
            ->assertCanSeeTableRecords(
                IncentiveAssistantLine::whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $calc->id))->get()
            );
    }
}
