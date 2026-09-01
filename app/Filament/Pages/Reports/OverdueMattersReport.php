<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\Court;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\PartyLeave;
use App\Models\Type;
use App\Services\IncentiveCalculatorService;
use App\Support\Sql;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Open matters ranked by how long they have been sitting with an assistant.
 *
 * The system already knew how to measure this — IncentiveCalculatorService
 * counts leave-aware working days for the incentive tiers — but only ever did
 * so inside a calculation run, never as a live list of what is running late.
 *
 * Calendar age is computed in SQL so it can be sorted and filtered cheaply.
 * Working days are computed per visible row, with every assistant's leave loaded
 * once up front rather than two queries per matter.
 */
class OverdueMattersReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.overdue-matters-report';

    /** @var Collection<int, Collection<int, PartyLeave>>|null */
    private ?Collection $leaveByParty = null;

    /** @var array<int, int|null> */
    private array $firstAssistantCache = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Overdue Matters');
    }

    public function getTitle(): string
    {
        return __('Overdue Matters');
    }

    /**
     * All leave, grouped by party, loaded once per request.
     */
    private function leaveFor(?int $partyId): Collection
    {
        $this->leaveByParty ??= PartyLeave::query()->get()->groupBy('party_id');

        if ($partyId === null) {
            return collect();
        }

        return $this->leaveByParty->get($partyId, collect());
    }

    /**
     * The matter's first-assigned assistant — the same one the incentive engine
     * uses for completion days, so the two never disagree.
     */
    private function firstAssistantId(Matter $matter): ?int
    {
        return $this->firstAssistantCache[$matter->id] ??= MatterParty::query()
            ->where('matter_id', $matter->id)
            ->where('role', 'expert')
            ->where('type', 'assistant')
            ->orderBy('id')
            ->value('party_id');
    }

    private function workingDaysOpen(Matter $matter): ?int
    {
        if (! $matter->distributed_at) {
            return null;
        }

        return app(IncentiveCalculatorService::class)->workingDaysBetween(
            Carbon::parse($matter->distributed_at),
            now(),
            $this->leaveFor($this->firstAssistantId($matter))
        );
    }

    protected function getTableQuery(): Builder
    {
        return Matter::query()
            ->with(['court', 'type', 'assistantsOnly.party'])
            ->whereNull('final_report_at')
            ->whereNotNull('distributed_at')
            ->select('matters.*')
            ->selectRaw(Sql::daysSince('matters.distributed_at').' as days_open');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('days_open', 'desc')
            ->emptyStateHeading(__('Nothing overdue'))
            ->emptyStateDescription(__('No open matter matches these filters.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn (Matter $record) => $record->year.'/'.$record->number)
                    ->url(fn (Matter $record) => MatterResource::getUrl('view', ['record' => $record]))
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn ($q) => $q->where('matters.number', 'like', "%{$search}%")
                            ->orWhere('matters.year', 'like', "%{$search}%")
                    )),

                TextColumn::make('court.name')
                    ->label(__('Court / Type'))
                    ->description(fn (Matter $record) => $record->type?->name)
                    ->wrap(),

                TextColumn::make('assistants')
                    ->label(__('Assistant'))
                    ->getStateUsing(fn (Matter $record) => $record->assistantsOnly
                        ->map(fn ($mp) => $mp->party?->name)->filter()->implode(', ') ?: '—')
                    ->wrap(),

                TextColumn::make('distributed_at')
                    ->label(__('Assigned'))
                    ->date()
                    ->sortable(),

                TextColumn::make('days_open')
                    ->label(__('Calendar Days'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state > 90 => 'danger',
                        (int) $state > 60 => 'warning',
                        (int) $state > 30 => 'info',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('working_days')
                    ->label(__('Working Days'))
                    ->description(__('Excludes weekends and the assistant\'s leave'))
                    ->getStateUsing(fn (Matter $record) => $this->workingDaysOpen($record) ?? '—')
                    ->alignEnd(),

                TextColumn::make('stage')
                    ->label(__('Stage'))
                    ->badge()
                    ->getStateUsing(fn (Matter $record) => $record->initial_report_at
                        ? __('Initial report submitted')
                        : __('No initial report'))
                    ->color(fn (Matter $record) => $record->initial_report_at ? 'info' : 'warning'),

                TextColumn::make('next_session_date')
                    ->label(__('Next Session'))
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('age')
                    ->label(__('Age'))
                    ->options([
                        '30' => __('Over 30 days'),
                        '60' => __('Over 60 days'),
                        '90' => __('Over 90 days'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $days) => $q->whereRaw(Sql::daysSince('matters.distributed_at').' > ?', [(int) $days])
                    )),

                Filter::make('no_initial_report')
                    ->label(__('No initial report yet'))
                    ->query(fn (Builder $query) => $query->whereNull('initial_report_at')),

                SelectFilter::make('court_id')
                    ->label(__('Court'))
                    ->options(fn () => Court::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type_id')
                    ->label(__('Matter Type'))
                    ->options(fn () => Type::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('assistant')
                    ->label(__('Assistant'))
                    ->options(fn () => Party::withRole('expert', 'assistant')
                        ->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $partyId) => $q->whereHas('assistantsOnly', fn ($a) => $a->where('party_id', $partyId))
                    )),
            ])
            ->filtersFormWidth(Width::ExtraLarge)
            ->persistSearchInSession()
            ->queryStringIdentifier('overdue');
    }
}
