<?php

namespace Tests\Feature;

use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Models\Matter;
use App\Models\Party;
use App\Models\User;
use App\Services\IncentiveCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * What the printed incentive statement can say about a matter's rate.
 *
 * The "Rate %" on a line belongs to the MATTER, so it reads identically on every
 * co-assistant's row. Where a matter is shared, the amount beside it is only a
 * fraction of what that rate implies — and the print said nothing about it, so a
 * 10% matter paying 150 of a 3,000 fee looked like an error. The on-screen
 * summary already showed both facts; these pin them onto the printed data.
 */
class IncentiveAssistantSummaryTest extends TestCase
{
    use RefreshDatabase;

    private IncentiveCalculation $calculation;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());

        $this->calculation = IncentiveCalculation::create([
            'name' => 'Test period',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'status' => 'draft',
        ]);
    }

    /**
     * One matter billed 3,000 at an effective 10%, shared by the given assistants.
     *
     * @param  array<int, array{party: Party, share: float, override: float|null}>  $shares
     */
    private function matterSharedBy(array $shares, float $fee = 3000): Matter
    {
        $matter = Matter::factory()->create();

        $line = IncentiveLine::create([
            'incentive_calculation_id' => $this->calculation->id,
            'matter_id' => $matter->id,
            'fee_amount_excl_vat' => $fee,
            'base_percentage' => 10,
            'committee_adjustment' => 0,
            'effective_percentage' => 10,
            'base_amount' => $fee * 0.10,
            'total_deduction_pct' => 0,
            'net_amount' => $fee * 0.10,
        ]);

        foreach ($shares as $share) {
            IncentiveAssistantLine::create([
                'incentive_line_id' => $line->id,
                'party_id' => $share['party']->id,
                'percentage_override' => $share['override'] ?? null,
                'share_amount' => $share['share'],
                'extra_percentage' => 0,
                'extra_amount' => 0,
                'minimum_penalty_pct' => 0,
                'minimum_penalty_amount' => 0,
                'total_amount' => $share['share'],
            ]);
        }

        return $matter;
    }

    /**
     * @return array<string, mixed>
     */
    private function matterRowFor(Party $party, Matter $matter): array
    {
        $summary = app(IncentiveCalculatorService::class)
            ->getAssistantSummary($this->calculation)
            ->firstWhere('party.id', $party->id);

        return collect($summary['matters'])->firstWhere('matter_id', $matter->id);
    }

    public function test_a_sole_assistant_keeps_the_matters_full_rate(): void
    {
        $party = Party::factory()->assistant()->create();
        $matter = $this->matterSharedBy([
            ['party' => $party, 'share' => 300.0, 'override' => null],
        ]);

        $row = $this->matterRowFor($party, $matter);

        $this->assertSame(1, $row['assistant_count']);
        // 300 of 3,000 is the matter's own 10%, so the view prints nothing extra.
        $this->assertEquals(10.0, $row['own_percentage']);
        $this->assertEquals(10.0, (float) $row['percentage']);
    }

    public function test_a_shared_matter_reports_the_split_and_each_assistants_own_cut(): void
    {
        $first = Party::factory()->assistant()->create();
        $second = Party::factory()->assistant()->create();

        $matter = $this->matterSharedBy([
            ['party' => $first, 'share' => 150.0, 'override' => null],
            ['party' => $second, 'share' => 150.0, 'override' => null],
        ]);

        foreach ([$first, $second] as $party) {
            $row = $this->matterRowFor($party, $matter);

            $this->assertSame(2, $row['assistant_count'], 'both assistants must see the matter as shared');
            // The matter is a 10% case; half the pool each is 5% of the fee.
            $this->assertEquals(10.0, (float) $row['percentage']);
            $this->assertEquals(5.0, $row['own_percentage']);
        }
    }

    public function test_an_uneven_split_reports_each_assistants_real_cut(): void
    {
        // commission_percentage weights split the pool 2:1.
        $major = Party::factory()->assistant()->create();
        $minor = Party::factory()->assistant()->create();

        $matter = $this->matterSharedBy([
            ['party' => $major, 'share' => 200.0, 'override' => null],
            ['party' => $minor, 'share' => 100.0, 'override' => null],
        ]);

        $this->assertEqualsWithDelta(6.67, $this->matterRowFor($major, $matter)['own_percentage'], 0.01);
        $this->assertEqualsWithDelta(3.33, $this->matterRowFor($minor, $matter)['own_percentage'], 0.01);
    }

    public function test_an_override_is_reported_alongside_the_matters_rate(): void
    {
        $party = Party::factory()->assistant()->create();
        $matter = $this->matterSharedBy([
            ['party' => $party, 'share' => 450.0, 'override' => 15.0],
        ]);

        $row = $this->matterRowFor($party, $matter);

        $this->assertEquals(15.0, (float) $row['percentage_override']);
        $this->assertEquals(10.0, (float) $row['percentage'], "the matter's own rate is still reported");
        $this->assertEquals(15.0, $row['own_percentage']);
    }

    public function test_a_matter_with_no_fee_reports_no_percentage_rather_than_dividing_by_zero(): void
    {
        $party = Party::factory()->assistant()->create();
        $matter = $this->matterSharedBy([
            ['party' => $party, 'share' => 0.0, 'override' => null],
        ], fee: 0);

        $this->assertNull($this->matterRowFor($party, $matter)['own_percentage']);
    }
}
