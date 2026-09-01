<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Matter;
use App\Models\MatterRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The "what needs a human today" panel.
 *
 * Every figure here was previously computable but never surfaced anywhere — the
 * data existed, no query asked for it. All four are plain aggregates so the
 * widget stays cheap enough for the landing page.
 */
class AttentionNeededWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    public function getColumns(): int|array
    {
        return 4;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Matter') ?? false;
    }

    protected function getStats(): array
    {
        $sessionsSoon = Matter::query()
            ->whereNotNull('next_session_date')
            ->whereBetween('next_session_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->count();

        // Approved for final reporting but not yet submitted.
        $awaitingFinalReport = Matter::query()
            ->whereNotNull('final_report_memo_date')
            ->whereNull('final_report_at')
            ->count();

        $pendingRequests = MatterRequest::query()
            ->whereIn('status', [RequestStatus::PENDING->value, RequestStatus::DISPUTED->value])
            ->count();

        // A matter nobody is assigned to earns no incentive and has no owner.
        $unassigned = Matter::query()
            ->whereDoesntHave('assistantsOnly')
            ->whereNull('final_report_at')
            ->count();

        return [
            Stat::make(__('Sessions This Week'), $sessionsSoon)
                ->description(__('Hearings in the next 7 days'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($sessionsSoon > 0 ? 'info' : 'gray')
                ->url(MatterResource::getUrl('index')),

            Stat::make(__('Awaiting Final Report'), $awaitingFinalReport)
                ->description(__('Memo approved, report not submitted'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color($awaitingFinalReport > 0 ? 'warning' : 'success'),

            Stat::make(__('Open Requests'), $pendingRequests)
                ->description(__('Pending or disputed'))
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($pendingRequests > 0 ? 'warning' : 'success'),

            Stat::make(__('Unassigned Matters'), $unassigned)
                ->description(__('Open with no assistant'))
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($unassigned > 0 ? 'danger' : 'success'),
        ];
    }
}
