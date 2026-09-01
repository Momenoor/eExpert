<?php

namespace App\Filament\Pages;

use App\Enums\MatterCollectionStatus;
use App\Filament\Exports\MatterExporter;
use App\Models\Matter;
use App\Models\Party;
use App\Support\Sql;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class MattersMonthlyReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|null|UnitEnum $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.matters-monthly-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Matters Monthly Report');
    }

    public function getTitle(): string
    {
        return __('Matters Monthly Report');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('period')
                    ->label(__('Month'))
                    ->getStateUsing(fn ($record) => Carbon::createFromFormat('Y-m', $record->period)->format('M Y'))
                    ->sortable(),
                TextColumn::make('total_matters')
                    ->label(__('New Matters'))
                    ->alignCenter()
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('initial_reports')
                    ->label(__('Initial Reports'))
                    ->alignCenter()
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('final_reports')
                    ->label(__('Final Reports'))
                    ->alignCenter()
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('total_fees')
                    ->label(__('Total Fees'))
                    ->money('AED')
                    ->alignEnd()
                    ->summarize(Sum::make()->label(__('Total'))),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->options(function () {
                        return Matter::query()
                            ->whereNotNull('year')
                            ->selectRaw('DISTINCT year')
                            ->orderBy('year', 'desc')
                            ->pluck('year', 'year')
                            ->toArray();
                    })
                    ->placeholder(__('All Years'))
                    ->default(date('Y'))
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('assistant')
                    ->label(__('Assistant'))
                    ->options(Party::withRole('expert', 'assistant')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                    )
                    ->placeholder(__('All Assistants'))
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('court')
                    ->relationship('court', 'name')
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('type')
                    ->relationship('type', 'name')
                    ->label(__('Matter Type'))
                    ->searchable()
                    ->preload()
                    ->query(fn (Builder $query) => $query),
                SelectFilter::make('collection_status')
                    ->label(__('Collection Status'))
                    ->options(MatterCollectionStatus::class)
                    ->multiple()
                    ->query(fn (Builder $query) => $query),
                Filter::make('distributed_at')
                    ->label(__('Received Date'))
                    ->schema([
                        Fieldset::make(__('Received Date'))->schema([
                            DatePicker::make('received_from')->label(__('From')),
                            DatePicker::make('received_until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(fn (Builder $query) => $query),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->headerActions([
                ExportAction::make()
                    ->exporter(MatterExporter::class)
                    ->label(__('Export Detailed Matters'))
                    ->modifyQueryUsing(fn (Builder $query) => $this->getDetailedQuery($query)),
            ]);
    }

    protected function getDetailedQuery(Builder $query): Builder
    {
        $filters = $this->tableFilters;

        return $query
            ->when($filters['year']['value'] ?? null, fn ($q, $year) => $q->where('year', $year))
            ->when($filters['assistant']['value'] ?? null, fn ($q, $assistantId) => $q->whereHas('matterParties', fn ($qp) => $qp->where('party_id', $assistantId)->where('role', 'expert')->where('type', 'assistant')))
            ->when($filters['court']['value'] ?? null, fn ($q, $courtId) => $q->where('court_id', $courtId))
            ->when($filters['type']['value'] ?? null, fn ($q, $typeId) => $q->where('type_id', $typeId))
            ->when($filters['collection_status']['values'] ?? null, fn ($q, $status) => $q->whereIn('collection_status', $status))
            ->when($filters['distributed_at']['received_from'] ?? null, fn ($q, $date) => $q->whereDate('distributed_at', '>=', $date))
            ->when($filters['distributed_at']['received_until'] ?? null, fn ($q, $date) => $q->whereDate('distributed_at', '<=', $date))
            ->with([
                'court',
                'type',
                'mainPartiesOnly.party',
                'mainPartiesOnly.representatives.party',
                'expertsOnly.party',
                'fees',
                'notes',
                'allocations',
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $filters = $this->tableFilters;

        // 1. Reusable helper to apply filters + Soft Deletes manually inside the queries.
        //
        // Every predicate here has to work on BOTH an Eloquent builder and a
        // plain DB::table() one, since the month list is built from the latter.
        // The assistant filter used whereHas(), which only exists on Eloquent,
        // so choosing an assistant threw a BadMethodCallException; whereExists
        // states the same thing and works on either.
        $applyFilters = function ($q) {
            $filters = $this->tableFilters;

            // Explicitly enforce soft deletes inside since we're breaking away from standard Eloquent scoping
            $q->whereNull('deleted_at');

            return $q->when($filters['year']['value'] ?? null, fn ($q, $year) => $q->where('year', $year))
                ->when($filters['assistant']['value'] ?? null, fn ($q, $assistantId) => $q->whereExists(
                    fn ($qe) => $qe->select(DB::raw(1))
                        ->from('matter_party')
                        ->whereColumn('matter_party.matter_id', 'matters.id')
                        ->where('matter_party.party_id', $assistantId)
                        ->where('matter_party.role', 'expert')
                        ->where('matter_party.type', 'assistant')
                ))
                ->when($filters['court']['value'] ?? null, fn ($q, $courtId) => $q->where('court_id', $courtId))
                ->when($filters['type']['value'] ?? null, fn ($q, $typeId) => $q->where('type_id', $typeId))
                ->when($filters['collection_status']['values'] ?? null, fn ($q, $status) => $q->whereIn('collection_status', $status))
                // The date range was read by the export but never by the table
                // itself, so narrowing it changed the exported file and left the
                // figures on screen exactly as they were.
                ->when($filters['distributed_at']['received_from'] ?? null, fn ($q, $date) => $q->whereDate('distributed_at', '>=', $date))
                ->when($filters['distributed_at']['received_until'] ?? null, fn ($q, $date) => $q->whereDate('distributed_at', '<=', $date));
        };

        // 2. Base query for all unique months (using clean, un-scoped queries to prevent automatic soft-deleting interference)
        $monthsQuery = DB::table('matters')
            ->selectRaw(Sql::yearMonth('distributed_at').' as period')
            ->whereNotNull('distributed_at')
            ->whereNull('deleted_at') // manual soft delete check
            ->tap($applyFilters)
            ->union(
                DB::table('matters')
                    ->selectRaw(Sql::yearMonth('initial_report_at').' as period')
                    ->whereNotNull('initial_report_at')
                    ->whereNull('deleted_at')
                    ->tap($applyFilters)
            )
            ->union(
                DB::table('matters')
                    ->selectRaw(Sql::yearMonth('final_report_at').' as period')
                    ->whereNotNull('final_report_at')
                    ->whereNull('deleted_at')
                    ->tap($applyFilters)
            );

        // fromSub, not DB::raw(toSql()) + mergeBindings. mergeBindings drops the
        // sub-query's bindings into the OUTER query's buckets, where they are
        // then flattened in select → from → where order. The month list appears
        // in the FROM clause but its bindings arrived in the `where` bucket, so
        // the moment any filter added a bound value the placeholders and the
        // values lined up against each other in the wrong order — a year filter
        // produced "year = 2026-%" and then an Invalid parameter number.
        $months = DB::query()
            ->fromSub($monthsQuery, 'all_months')
            ->select('period')
            ->distinct();

        // 3. Construct subqueries for counts/sums
        $newMattersSub = Matter::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw(Sql::yearMonth('distributed_at').' = months.period')
            ->tap($applyFilters);

        $initialReportsSub = Matter::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw(Sql::yearMonth('initial_report_at').' = months.period')
            ->tap($applyFilters);

        $finalReportsSub = Matter::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw(Sql::yearMonth('final_report_at').' = months.period')
            ->tap($applyFilters);

        $feesSub = DB::table('fees')
            ->join('matters', 'fees.matter_id', '=', 'matters.id')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereRaw(Sql::yearMonth('matters.distributed_at').' = months.period')
            ->whereNull('matters.deleted_at') // Protect the join from deleted matters
            ->when($filters['year']['value'] ?? null, fn ($q, $year) => $q->where('matters.year', $year))
            ->when($filters['assistant']['value'] ?? null, fn ($q, $assistantId) => $q->whereExists(fn ($qe) => $qe->select(DB::raw(1))->from('matter_party')->whereColumn('matter_party.matter_id', 'matters.id')->where('matter_party.party_id', $assistantId)->where('role', 'expert')->where('type', 'assistant')))
            ->when($filters['court']['value'] ?? null, fn ($q, $courtId) => $q->where('matters.court_id', $courtId))
            ->when($filters['type']['value'] ?? null, fn ($q, $typeId) => $q->where('matters.type_id', $typeId))
            ->when($filters['collection_status']['values'] ?? null, fn ($q, $status) => $q->whereIn('matters.collection_status', $status))
            ->when($filters['distributed_at']['received_from'] ?? null, fn ($q, $date) => $q->whereDate('matters.distributed_at', '>=', $date))
            ->when($filters['distributed_at']['received_until'] ?? null, fn ($q, $date) => $q->whereDate('matters.distributed_at', '<=', $date));

        // 4. Build master query out of DB::query() to avoid model traits appending extra SQL logic
        $mainQuery = DB::query()
            ->fromSub($months, 'months')
            // The period doubles as the row key. Filament always appends the
            // model's qualified key as a sort tiebreaker unless an order on a
            // column named `id` is already present, and this derived table has
            // no `matters.id` to append — which made the whole page fail with
            // "Unknown column 'matters.id' in 'order clause'".
            ->select('months.period')
            ->selectRaw('months.period as id')
            ->selectSub($newMattersSub, 'total_matters')
            ->selectSub($initialReportsSub, 'initial_reports')
            ->selectSub($finalReportsSub, 'final_reports')
            ->selectSub($feesSub, 'total_fees');

        // Bound, not interpolated: $filters comes from Livewire request state and
        // Filament does not validate a SelectFilter value against its option list
        // server-side, so this string reaches the query exactly as the user sent it.
        if ($year = $filters['year']['value'] ?? null) {
            $mainQuery->whereRaw('months.period LIKE ?', [((int) $year).'-%']);
        }

        $mainQuery->orderBy('months.period', 'desc');

        // 5. Hydrate it back to a clean Eloquent builder that Filament can paginate safely without breaking syntax
        return Matter::query()
            ->fromSub($mainQuery, 'report_table')
            ->withTrashed(); // 🌟 CRITICAL: This tells Eloquent "Do not append deleted_at to the outer query"
    }
}
