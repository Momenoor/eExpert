<?php

namespace App\Filament\Widgets;

use App\Models\MatterParty;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class AssistantMattersCountChartWidget extends ChartWidget
{
    protected ?string $heading = 'Assistant Matters Count Chart';

    //    protected ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return __('Assistant Matters Count');
    }

    protected function getData(): array
    {
        $assistants = MatterParty::query()
            ->where('matter_party.role', 'expert')  // ✅ Added missing role filter
            ->where('matter_party.type', 'assistant')
            ->whereHas('party', fn ($q) => $q->whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
            )
            ->whereHas('matter', fn ($q) => $q->whereNull('final_report_memo_date')
                ->whereNull('final_report_at')
            )
            ->with('party:id,name')
            ->get()
            ->groupBy('party_id');

        // ✅ Generate enough colors for all assistants dynamically
        $colors = collect([
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
            '#8b5cf6', '#06b6d4', '#f97316', '#84cc16',
            '#ec4899', '#14b8a6',
        ]); // cycle repeats if more than 10 assistants

        return [
            'datasets' => [
                [
                    'label' => __('Active Matters').' + '.__('Initial Report'),
                    'data' => array_values($assistants->map->count()->toArray()),
                    // ✅ ids array is now included so the JS click handler can read it
                    'ids' => array_values($assistants->keys()->toArray()),
                    'backgroundColor' => $colors->take($assistants->count())->toArray(),
                    'borderColor' => $colors->take($assistants->count())->toArray(),
                    'borderWidth' => 2,
                    'borderRadius' => 15,
                    'animation' => [
                        'duration' => 1000,
                        'easing' => 'easeOutQuart',
                    ],
                ],

            ],
            'labels' => array_values(
                $assistants->map(fn ($group) => $group->first()->party->name)->toArray()
            ),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
            onHover: (event, elements) => {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                    onClick: (event, elements, chart) => {
                    if (elements.length > 0) {
                        // 1. Get the index of the specific bar clicked
                        const index = elements[0].index;

                        // 2. Pull the ID and Name from the specific index in the datasets/labels
                        const partyId = chart.data.datasets[0].ids[index];
                        const assistantName = chart.data.labels[index];

                        if (window.Livewire) {
                            window.Livewire.dispatch('filterTableByAssistant', {
                                partyId: partyId,
                                assistantName: assistantName
                            });
                         }
                        }
                    }
                    }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
