<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\Matter;
use App\Support\Sql;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * An assistant's own workload and deadlines.
 *
 * Every other report answers a manager's question. This one answers the
 * assistant's: what is on my desk, what is overdue, what is in court next.
 *
 * Scoped by the signed-in user's linked Party and fails CLOSED — a user with no
 * Party link sees nothing rather than everything. That is the same trap that
 * made MatterResource leak every matter in the office when the link was missing.
 */
class MyMattersReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.my-matters-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('My Matters');
    }

    public function getTitle(): string
    {
        return __('My Matters');
    }

    /**
     * Anyone with a Party link may see their own work. No extra permission is
     * required precisely because the page can only ever show the viewer's own
     * matters.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->party !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function getTableQuery(): Builder
    {
        $partyId = auth()->user()?->party?->id;

        $query = Matter::query()
            ->with(['court', 'type'])
            ->select('matters.*')
            ->selectRaw(Sql::daysSince('matters.distributed_at').' as days_open');

        if (! $partyId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('matterParties', fn ($q) => $q->where('party_id', $partyId));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('next_session_date', 'asc')
            ->emptyStateHeading(__('Nothing assigned to you'))
            ->emptyStateDescription(__('No matter matches these filters.'))
            ->emptyStateIcon('heroicon-o-briefcase')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn (Matter $record) => $record->year.'/'.$record->number)
                    ->url(fn (Matter $record) => MatterResource::getUrl('view', ['record' => $record]))
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn ($q) => $q->where('number', 'like', "%{$search}%")
                            ->orWhere('year', 'like', "%{$search}%")
                    )),

                TextColumn::make('court.name')
                    ->label(__('Court / Type'))
                    ->description(fn (Matter $record) => $record->type?->name)
                    ->wrap(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->getLabel()),

                TextColumn::make('next_session_date')
                    ->label(__('Next Session'))
                    ->dateTime('D, d M Y')
                    ->description(fn (Matter $record) => $record->next_session_date?->isFuture()
                        ? $record->next_session_date->diffForHumans()
                        : null)
                    ->color(fn (Matter $record) => $record->next_session_date?->isToday() ? 'danger' : null)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('distributed_at')
                    ->label(__('Assigned'))
                    ->date()
                    ->sortable(),

                TextColumn::make('days_open')
                    ->label(__('Days Open'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state > 90 => 'danger',
                        (int) $state > 60 => 'warning',
                        default => 'success',
                    })
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('initial_report_at')
                    ->label(__('Initial Report'))
                    ->date()
                    ->placeholder(__('Not submitted'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('final_report_at')
                    ->label(__('Final Report'))
                    ->date()
                    ->placeholder(__('Not submitted'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('open_only')
                    ->label(__('Open only'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('final_report_at')),

                Filter::make('needs_initial_report')
                    ->label(__('Needs initial report'))
                    ->query(fn (Builder $query) => $query->whereNull('initial_report_at')),

                Filter::make('session_this_week')
                    ->label(__('Session within 7 days'))
                    ->query(fn (Builder $query) => $query->whereNotNull('next_session_date')
                        ->whereBetween('next_session_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])),
            ])
            ->filtersFormWidth(Width::Medium)
            ->queryStringIdentifier('my_matters');
    }
}
