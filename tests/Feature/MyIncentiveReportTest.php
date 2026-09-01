<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\MyIncentiveReport;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Models\Matter;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An assistant's own incentive statement, and only their own.
 *
 * The page is pinned to the signed-in user's Party rather than filtered by it,
 * so these check the boundary holds even with the gate wide open — a scoping
 * bug that only showed up under a restrictive gate would be invisible on the
 * day someone's role changed.
 */
class MyIncentiveReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Open the gate for tests about SCOPING, so a leak cannot hide behind a
     * permission check. Called per test rather than in setUp(): Gate::before
     * callbacks accumulate and the first non-null answer wins, so a permissive
     * one registered here would make the authorization tests below unable to
     * observe a denial at all.
     */
    private function allowEverything(): void
    {
        Gate::before(fn () => true);
    }

    private function calculation(string $status = 'finalized', string $name = 'January'): IncentiveCalculation
    {
        return IncentiveCalculation::create([
            'name' => $name,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'status' => $status,
            'finalized_at' => $status === 'finalized' ? now() : null,
        ]);
    }

    private function lineFor(IncentiveCalculation $calculation, Party $party, float $share, float $fee = 3000): IncentiveAssistantLine
    {
        $line = IncentiveLine::create([
            'incentive_calculation_id' => $calculation->id,
            'matter_id' => Matter::factory()->create()->id,
            'fee_amount_excl_vat' => $fee,
            'base_percentage' => 10,
            'committee_adjustment' => 0,
            'effective_percentage' => 10,
            'base_amount' => $fee * 0.10,
            'total_deduction_pct' => 0,
            'net_amount' => $fee * 0.10,
        ]);

        return IncentiveAssistantLine::create([
            'incentive_line_id' => $line->id,
            'party_id' => $party->id,
            'share_amount' => $share,
            'extra_percentage' => 0,
            'extra_amount' => 0,
            'minimum_penalty_pct' => 0,
            'minimum_penalty_amount' => 0,
            'total_amount' => $share,
        ]);
    }

    private function assistantUser(): array
    {
        $user = User::factory()->create();
        $party = Party::factory()->assistant()->create(['user_id' => $user->id]);

        return [$user, $party];
    }

    public function test_an_assistant_sees_only_their_own_lines(): void
    {
        $this->allowEverything();

        [$user, $mine] = $this->assistantUser();
        [, $theirs] = $this->assistantUser();

        $calculation = $this->calculation();
        $ownLine = $this->lineFor($calculation, $mine, 300);
        $otherLine = $this->lineFor($calculation, $theirs, 900);

        $this->actingAs($user);

        Livewire::test(MyIncentiveReport::class)
            ->assertCanSeeTableRecords([$ownLine])
            ->assertCanNotSeeTableRecords([$otherLine]);
    }

    public function test_the_scope_cannot_be_widened_through_the_filter(): void
    {
        $this->allowEverything();

        [$user, $mine] = $this->assistantUser();
        [, $theirs] = $this->assistantUser();

        $calculation = $this->calculation();
        $this->lineFor($calculation, $mine, 300);
        $otherLine = $this->lineFor($calculation, $theirs, 900);

        $this->actingAs($user);

        // The only filter is the calculation picker; pointing it anywhere still
        // cannot surface another party's line.
        Livewire::test(MyIncentiveReport::class)
            ->filterTable('calculation', $calculation->id)
            ->assertCanNotSeeTableRecords([$otherLine]);
    }

    public function test_draft_calculations_are_not_shown(): void
    {
        $this->allowEverything();

        [$user, $mine] = $this->assistantUser();

        $draft = $this->calculation('draft', 'Draft period');
        $draftLine = $this->lineFor($draft, $mine, 500);

        $this->actingAs($user);

        $component = Livewire::test(MyIncentiveReport::class);

        $this->assertNull($component->instance()->selectedCalculationId());
        $component->assertCanNotSeeTableRecords([$draftLine]);
    }

    public function test_each_calculation_is_reported_separately(): void
    {
        $this->allowEverything();

        [$user, $mine] = $this->assistantUser();

        $january = $this->calculation(name: 'January');
        $februaryCalc = IncentiveCalculation::create([
            'name' => 'February',
            'period_start' => '2026-02-01',
            'period_end' => '2026-02-28',
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        $januaryLine = $this->lineFor($january, $mine, 300);
        $februaryLine = $this->lineFor($februaryCalc, $mine, 700);

        $this->actingAs($user);

        // Newest period first by default.
        Livewire::test(MyIncentiveReport::class)
            ->assertCanSeeTableRecords([$februaryLine])
            ->assertCanNotSeeTableRecords([$januaryLine]);

        Livewire::test(MyIncentiveReport::class)
            ->filterTable('calculation', $january->id)
            ->assertCanSeeTableRecords([$januaryLine])
            ->assertCanNotSeeTableRecords([$februaryLine]);
    }

    public function test_period_totals_come_from_this_assistants_own_extras(): void
    {
        $this->allowEverything();

        [$user, $mine] = $this->assistantUser();
        [, $theirs] = $this->assistantUser();

        $calculation = $this->calculation();
        $this->lineFor($calculation, $mine, 300);
        $this->lineFor($calculation, $theirs, 900);

        IncentiveAssistantExtra::create([
            'incentive_calculation_id' => $calculation->id,
            'party_id' => $mine->id,
            'completed_matter_count' => 1,
            'meets_minimum' => true,
            'extra_amount' => 50,
            'penalty_amount' => 0,
            'fixed_deduction' => 20,
        ]);
        IncentiveAssistantExtra::create([
            'incentive_calculation_id' => $calculation->id,
            'party_id' => $theirs->id,
            'completed_matter_count' => 1,
            'meets_minimum' => true,
            'extra_amount' => 999,
            'penalty_amount' => 0,
            'fixed_deduction' => 0,
        ]);

        $this->actingAs($user);

        $page = Livewire::test(MyIncentiveReport::class)->instance();

        $this->assertEquals(50.0, (float) $page->periodTotals()->extra_amount);
        $this->assertEquals(300.0, $page->shareTotal());
        // 300 earned less the 20 fixed deduction.
        $this->assertEquals(280.0, $page->netTotal());
    }

    public function test_an_assistant_may_print_their_own_statement_but_not_another_persons(): void
    {
        // No gate opened here: the route has to stand on its own, because it
        // used to carry nothing but `auth` and any signed-in user could read
        // anyone's pay by editing the party id in the URL.
        [$user, $mine] = $this->assistantUser();
        [, $theirs] = $this->assistantUser();

        $calculation = $this->calculation();
        $this->lineFor($calculation, $mine, 300);
        $this->lineFor($calculation, $theirs, 900);

        $this->actingAs($user);

        $this->get(route('incentive.calculation.print.assistant', [
            'calculation' => $calculation->id,
            'party' => $mine->id,
        ]))->assertOk();

        $this->get(route('incentive.calculation.print.assistant', [
            'calculation' => $calculation->id,
            'party' => $theirs->id,
        ]))->assertForbidden();
    }

    public function test_the_whole_office_print_needs_the_print_permission(): void
    {
        [$user, $mine] = $this->assistantUser();
        $calculation = $this->calculation();
        $this->lineFor($calculation, $mine, 300);

        $this->actingAs($user);

        $this->get(route('incentive.calculation.print', ['calculation' => $calculation->id]))
            ->assertForbidden();
    }

    public function test_a_user_without_a_party_link_cannot_reach_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(MyIncentiveReport::canAccess());
        $this->assertFalse(MyIncentiveReport::shouldRegisterNavigation());
    }
}
