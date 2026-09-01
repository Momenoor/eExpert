<?php

namespace Tests\Feature;

use App\Filament\Resources\Matters\Pages\ViewMatter;
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
 * The Incentive section on a matter's own page showed "Net Amount" — the
 * office's fee × rate − deductions, before any per-assistant rate is applied —
 * directly beside "Assistant Shares" — what actually gets paid, including that
 * rate and any monthly bonus or penalty — with nothing to say the two numbers
 * belong to different stages of the same calculation. They are not required to
 * match (an assistant rate other than 100%, or a bonus/penalty, means they
 * usually won't), and a reader comparing "12,000.00" against "21,600.00" right
 * next to it reasonably reads that as broken math. Confirmed against production
 * data (matter 571/2009's finalized "Previous Periods" calculation) before
 * writing this: the underlying figures were correct, only unexplained.
 */
class MatterIncentiveDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
    }

    private function finalizedLineFor(Matter $matter, float $netAmount): IncentiveLine
    {
        $calculation = IncentiveCalculation::create([
            'name' => 'Finalized period',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);

        return IncentiveLine::create([
            'incentive_calculation_id' => $calculation->id,
            'matter_id' => $matter->id,
            'fee_amount_excl_vat' => 150000,
            'base_percentage' => 8,
            'committee_adjustment' => 0,
            'effective_percentage' => 8,
            'base_amount' => $netAmount,
            'total_deduction_pct' => 0,
            'net_amount' => $netAmount,
        ]);
    }

    public function test_the_base_and_the_payout_are_labelled_as_different_figures(): void
    {
        $matter = Matter::factory()->create();
        $line = $this->finalizedLineFor($matter, netAmount: 12000);

        $party = Party::factory()->assistant()->create(['name' => 'Amr']);

        // A rate other than 100% (or a bonus/penalty) is exactly why this
        // legitimately differs from the base above — it is not a bug.
        IncentiveAssistantLine::create([
            'incentive_line_id' => $line->id,
            'party_id' => $party->id,
            'share_amount' => 21600,
            'extra_percentage' => 0,
            'extra_amount' => 0,
            'minimum_penalty_pct' => 0,
            'minimum_penalty_amount' => 0,
            'total_amount' => 21600,
        ]);

        $html = Livewire::test(ViewMatter::class, ['record' => $matter->getRouteKey()])->html();

        $this->assertStringContainsString('Incentive Base', $html);
        $this->assertStringContainsString('Paid to Assistants', $html);
        $this->assertStringNotContainsString('Net Amount', $html, 'the old, unexplained label must be gone');
        $this->assertStringNotContainsString('Assistant Shares', $html, 'the old, unexplained label must be gone');

        // Both real figures are still shown — this fixes the explanation, not
        // the numbers.
        $this->assertStringContainsString('12,000.00', $html);
        $this->assertStringContainsString('21,600.00', $html);

        // The one thing a reader could not previously tell: that these two are
        // not supposed to match.
        $this->assertStringContainsString('not the amount paid out', $html);
        $this->assertStringContainsString('will not equal the base', $html);
    }

    public function test_a_manual_override_is_still_flagged_next_to_the_payout(): void
    {
        $matter = Matter::factory()->create();
        $line = $this->finalizedLineFor($matter, netAmount: 5000);
        $party = Party::factory()->assistant()->create(['name' => 'Nahla']);

        IncentiveAssistantLine::create([
            'incentive_line_id' => $line->id,
            'party_id' => $party->id,
            'percentage_override' => 15,
            'share_amount' => 7500,
            'extra_percentage' => 0,
            'extra_amount' => 0,
            'minimum_penalty_pct' => 0,
            'minimum_penalty_amount' => 0,
            'total_amount' => 7500,
        ]);

        $html = Livewire::test(ViewMatter::class, ['record' => $matter->getRouteKey()])->html();

        $this->assertStringContainsString('override', $html);
    }

    public function test_the_total_sums_every_assistant_on_a_shared_matter(): void
    {
        $matter = Matter::factory()->create();
        $line = $this->finalizedLineFor($matter, netAmount: 4000);

        foreach ([['Nahla', 2000], ['Amr', 2000]] as [$name, $share]) {
            IncentiveAssistantLine::create([
                'incentive_line_id' => $line->id,
                'party_id' => Party::factory()->assistant()->create(['name' => $name])->id,
                'share_amount' => $share,
                'extra_percentage' => 0,
                'extra_amount' => 0,
                'minimum_penalty_pct' => 0,
                'minimum_penalty_amount' => 0,
                'total_amount' => $share,
            ]);
        }

        $html = Livewire::test(ViewMatter::class, ['record' => $matter->getRouteKey()])->html();

        $this->assertStringContainsString('Total: 4,000.00', $html);
    }
}
