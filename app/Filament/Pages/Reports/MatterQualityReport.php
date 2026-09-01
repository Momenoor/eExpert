<?php

namespace App\Filament\Pages\Reports;

use App\Enums\MatterDifficulty;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Court;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Type;
use App\Services\IncentiveCalculatorService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Rework and quality signals per matter.
 *
 * review_count, has_substantive_changes and has_court_penalty are recorded on
 * every matter and drive real deductions in the incentive engine, but nothing
 * ever aggregated them — you could see them one matter at a time or feel them in
 * a payroll figure, never review them as a set.
 *
 * Late-final-report days use the same measure the deduction itself uses
 * (working days from the memo date to submission), so this report and the
 * incentive calculation can never tell different stories.
 */
class MatterQualityReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.matter-quality-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Quality & Rework');
    }

    public function getTitle(): string
    {
        return __('Quality & Rework');
    }
    public function getTablePluralModelLabel(): string
    {
        return __('matters');
    }

    /**
     * Working days between the final-report memo and its submission — the exact
     * measure calculateDeductions() uses for the late-report deduction.
     */
    private function lateDays(Matter $matter): ?int
    {
        if (! $matter->final_report_memo_date || ! $matter->final_report_at) {
            return null;
        }

        return app(IncentiveCalculatorService::class)->workingDaysBetween(
            Carbon::parse($matter->final_report_memo_date),
            Carbon::parse($matter->final_report_at)
        );
    }

    protected function getTableQuery(): Builder
    {
        return Matter::query()
            ->with(['court', 'type', 'assistantsOnly.party'])
            ->where(fn (Builder $q) => $q
                ->where('review_count', '>', 0)
                ->orWhere('has_substantive_changes', true)
                ->orWhere('has_court_penalty', true)
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('review_count', 'desc')
            ->emptyStateHeading(__('No quality issues recorded'))
            ->emptyStateDescription(__('No matter matches these filters.'))
            ->emptyStateIcon('heroicon-o-check-badge')
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

                TextColumn::make('assistants')
                    ->label(__('Assistant'))
                    ->getStateUsing(fn (Matter $record) => $record->assistantsOnly
                        ->map(fn ($mp) => $mp->party?->name)->filter()->implode(', ') ?: '—')
                    ->wrap(),

                TextColumn::make('difficulty')
                    ->label(__('Difficulty'))
                    ->badge(),

                TextColumn::make('review_count')
                    ->label(__('Reviews'))
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state >= 2 => 'danger',
                        (int) $state === 1 => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                IconColumn::make('has_substantive_changes')
                    ->label(__('Substantive Changes'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('has_court_penalty')
                    ->label(__('Court Penalty'))
                    ->boolean()
                    ->trueColor('danger')
                    ->sortable(),

                TextColumn::make('late_days')
                    ->label(__('Final Report Lateness'))
                    ->description(__('Working days from memo to submission'))
                    ->getStateUsing(fn (Matter $record) => $this->lateDays($record) ?? '—')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === '—' => 'gray',
                        (int) $state > 10 => 'danger',
                        (int) $state > 4 => 'warning',
                        default => 'success',
                    })
                    ->alignEnd(),

                TextColumn::make('final_report_at')
                    ->label(__('Final Report'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('repeat_reviews')
                    ->label(__('Reviewed twice or more'))
                    ->query(fn (Builder $query) => $query->where('review_count', '>=', 2)),

                Filter::make('court_penalty')
                    ->label(__('Court penalty only'))
                    ->query(fn (Builder $query) => $query->where('has_court_penalty', true)),

                Filter::make('substantive_changes')
                    ->label(__('Substantive changes only'))
                    ->query(fn (Builder $query) => $query->where('has_substantive_changes', true)),

                SelectFilter::make('difficulty')
                    ->label(__('Difficulty'))
                    ->options(MatterDifficulty::class),

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

                Filter::make('final_report_between')
                    ->label(__('Final Report Date'))
                    ->schema([
                        Section::make(__('Final Report Date'))->schema([
                            DatePicker::make('from')->label(__('From')),
                            DatePicker::make('until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('final_report_at', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('final_report_at', '<=', $v))
                    ),
            ])
            ->filtersFormWidth(Width::ExtraLarge)
            ->persistSearchInSession()
            ->queryStringIdentifier('quality');
    }
}
