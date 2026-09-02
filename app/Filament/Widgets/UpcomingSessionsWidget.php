<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\Matter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Court hearings in the next two weeks.
 *
 * next_session_date was filterable on the matters table and shown as a column,
 * but never surfaced as "what is coming up" — the question people actually ask.
 */
class UpcomingSessionsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('ViewAny:Matter') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return Matter::query()
            ->with(['court', 'type', 'assistantsOnly.party'])
            ->whereNotNull('next_session_date')
            ->whereBetween('next_session_date', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
            ->orderBy('next_session_date');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Upcoming Sessions'))
            ->description(__('Hearings scheduled in the next 14 days'))
            ->query($this->getTableQuery())
            // Unconditionally tighter, not the app's usual .custom-compact-table
            // (that class only kicks in below 1280px, so on an ordinary desktop
            // it does nothing) — a dashboard glance widget should read compact
            // at any width, not just on narrow screens.
            ->extraAttributes(['class' => 'fi-dashboard-compact-table'])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('No sessions scheduled'))
            ->emptyStateDescription(__('Nothing is listed for the next 14 days.'))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                TextColumn::make('next_session_date')
                    ->label(__('Session'))
                    // Weekday spelled out and a separate relative-time line both
                    // cost vertical space for a fact that fits one short badge:
                    // "Today · 14:00" reads faster than a full date plus a
                    // second line saying "in 3 days" underneath it.
                    ->badge()
                    ->formatStateUsing(fn (Matter $record) => $record->next_session_date?->isToday()
                        ? __('Today').' · '.$record->next_session_date->format('H:i')
                        : ($record->next_session_date?->isTomorrow()
                            ? __('Tomorrow').' · '.$record->next_session_date->format('H:i')
                            : $record->next_session_date?->format('d M · H:i')))
                    ->color(fn (Matter $record) => match (true) {
                        $record->next_session_date?->isToday() => 'danger',
                        $record->next_session_date?->isTomorrow() => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn (Matter $record) => $record->year.'/'.$record->number)
                    ->url(fn (Matter $record) => MatterResource::getUrl('view', ['record' => $record]))
                    ->weight('bold'),

                TextColumn::make('court.name')
                    ->label(__('Court'))
                    ->description(fn (Matter $record) => $record->type?->name)
                    ->wrap(),

                TextColumn::make('assistants')
                    ->label(__('Assistant'))
                    ->getStateUsing(fn (Matter $record) => $record->assistantsOnly
                        ->map(fn ($mp) => $mp->party?->name)
                        ->filter()
                        ->implode(', ') ?: '—'),
            ]);
    }
}
