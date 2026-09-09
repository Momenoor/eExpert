<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\Matters\Pages\ListMatters;
use App\Filament\Widgets\AttentionNeededWidget;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two filters AttentionNeededWidget's stat links depend on — added
 * specifically so each dashboard count has somewhere exact to link to.
 */
class MattersTableAttentionFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
    }

    public function test_awaiting_final_report_filter_matches_the_widgets_own_count(): void
    {
        $awaiting = Matter::factory()->create([
            'final_report_memo_date' => now()->subDays(5),
            'final_report_at' => null,
        ]);
        $notAwaiting = Matter::factory()->create([
            'final_report_memo_date' => null,
            'final_report_at' => null,
        ]);

        Livewire::test(ListMatters::class)
            ->filterTable('awaiting_final_report')
            ->assertCanSeeTableRecords([$awaiting])
            ->assertCanNotSeeTableRecords([$notAwaiting]);
    }

    public function test_unassigned_filter_matches_the_widgets_own_count(): void
    {
        $unassigned = Matter::factory()->create(['final_report_at' => null]);

        $assignedMatter = Matter::factory()->create(['final_report_at' => null]);
        $assistant = Party::factory()->assistant()->create();
        MatterParty::create([
            'matter_id' => $assignedMatter->id,
            'party_id' => $assistant->id,
            'role' => 'expert',
            'type' => 'assistant',
        ]);

        Livewire::test(ListMatters::class)
            ->filterTable('unassigned')
            ->assertCanSeeTableRecords([$unassigned])
            ->assertCanNotSeeTableRecords([$assignedMatter]);
    }

    public function test_attention_widget_stats_link_into_the_filters_that_produced_their_own_counts(): void
    {
        Matter::factory()->create([
            'final_report_memo_date' => now()->subDays(5),
            'final_report_at' => null,
        ]);

        $method = new \ReflectionMethod(AttentionNeededWidget::class, 'getStats');
        $method->setAccessible(true);
        $stats = $method->invoke(new AttentionNeededWidget);

        $urls = collect($stats)->map(fn ($stat) => $stat->getUrl());

        $this->assertTrue($urls->contains(fn (?string $url) => $url && str_contains($url, 'awaiting_final_report')));
        $this->assertTrue($urls->contains(fn (?string $url) => $url && str_contains($url, 'unassigned')));
        $this->assertTrue($urls->contains(fn (?string $url) => $url && str_contains($url, 'next_session_date')));
        $this->assertTrue($urls->contains(fn (?string $url) => $url && str_contains($url, 'matter-requests')));
    }
}
