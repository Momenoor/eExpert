<?php

namespace App\Filament\Widgets;

use App\Enums\MatterCollectionStatus;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Matter;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MatterStatsWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Matter') ?? false;
    }

    public function getColumns(): int|array
    {
        return 4;
    }

    protected function getStats(): array
    {
        // Aggregates, not Matter::all(): the previous version hydrated every
        // matter (with its enum casts and activity-log trait) on every dashboard
        // load just to count three of them. `status` is a computed accessor, not
        // a column, so it is expressed here as the null-checks it derives from.
        $totalCount = Matter::count();

        $unpaidCount = Matter::query()
            ->whereIn('collection_status', [MatterCollectionStatus::UNPAID, MatterCollectionStatus::PARTIAL])
            ->whereNotNull('final_report_at')
            ->count();

        $currentCount = Matter::whereNull('initial_report_at')->count();

        $submittedCount = Matter::query()
            ->whereNotNull('initial_report_at')
            ->whereNotNull('final_report_at')
            ->count();

        return [
            Stat::make(__('Total Matters'), $totalCount)
                ->description(__('Total matters in the system'))
                ->descriptionIcon('heroicon-m-briefcase')
                ->color(Color::Indigo),
            Stat::make(__('Unpaid Matters'), $unpaidCount)
                ->description(__('Total Matters pending payment'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger')
                ->url(MatterResource::getUrl('index', [
                    'tab' => 'final_submitted',
                    'filters' => [
                        'collection_status' => ['values' => ['unpaid', 'partial']],
                    ],
                ])),
            Stat::make(__('In Progress Matters'), $currentCount)
                ->description(__('Total Ongoing matters'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
            Stat::make(__('Finalized Matters'), $submittedCount)
                ->description(__('Total Finalized matters'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color(Color::Green),
        ];
    }
}
