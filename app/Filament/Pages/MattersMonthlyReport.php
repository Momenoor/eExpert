<?php

namespace App\Filament\Pages;

use App\Models\Matter;
use App\Models\Party;
use App\Models\Type;
use App\Models\Court;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

use App\Filament\Exports\MatterExporter;
use App\Enums\MatterCollectionStatus;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Tables\Columns\Summarizers\Sum;

class MattersMonthlyReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

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
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('year_month')
                    ->label(__('Month'))
                    ->getStateUsing(fn($record) => \Carbon\Carbon::createFromFormat('Y-m', $record->year_month)->format('M Y'))
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
                    ->query(fn(Builder $query) => $query),
                SelectFilter::make('assistant')
                    ->label(__('Assistant'))
                    ->options(Party::whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                        ->orderBy('name')
                        ->pluck('name', 'id')
                    )
                    ->placeholder(__('All Assistants'))
                    ->query(fn(Builder $query) => $query),
                SelectFilter::make('court')
                    ->relationship('court', 'name')
                    ->searchable()
                    ->preload()
                    ->query(fn(Builder $query) => $query),
                SelectFilter::make('type')
                    ->relationship('type', 'name')
                    ->label(__('Matter Type'))
                    ->searchable()
                    ->preload()
                    ->query(fn(Builder $query) => $query),
                SelectFilter::make('collection_status')
                    ->label(__('Collection Status'))
                    ->options(MatterCollectionStatus::class)
                    ->multiple()
                    ->query(fn(Builder $query) => $query),
                Filter::make('distributed_at')
                    ->label(__('Received Date'))
                    ->schema([
                        Fieldset::make(__('Received Date'))->schema([
                            DatePicker::make('received_from')->label(__('From')),
                            DatePicker::make('received_until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(fn(Builder $query) => $query),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->headerActions([
                ExportAction::make()
                    ->exporter(MatterExporter::class)
                    ->label(__('Export Detailed Matters'))
                    ->modifyQueryUsing(fn(Builder $query) => $this->getDetailedQuery($query)),
            ]);
    }

    protected function getDetailedQuery(Builder $query): Builder
    {
        $filters = $this->tableFilters;

        return $query
            ->when($filters['year']['value'] ?? null, fn($q, $year) => $q->where('year', $year))
            ->when($filters['assistant']['value'] ?? null, fn($q, $assistantId) => $q->whereHas('matterParties', fn($qp) => $qp->where('party_id', $assistantId)->where('role', 'expert')->where('type', 'assistant')))
            ->when($filters['court']['value'] ?? null, fn($q, $courtId) => $q->where('court_id', $courtId))
            ->when($filters['type']['value'] ?? null, fn($q, $typeId) => $q->where('type_id', $typeId))
            ->when($filters['collection_status']['values'] ?? null, fn($q, $status) => $q->whereIn('collection_status', $status))
            ->when($filters['distributed_at']['received_from'] ?? null, fn($q, $date) => $q->whereDate('distributed_at', '>=', $date))
            ->when($filters['distributed_at']['received_until'] ?? null, fn($q, $date) => $q->whereDate('distributed_at', '<=', $date))
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

// 1. Reusable helper to apply filters + Soft Deletes manually inside the queries
        $applyFilters = function ($q) {
            $filters = $this->tableFilters;

            // Explicitly enforce soft deletes inside since we're breaking away from standard Eloquent scoping
            $q->whereNull('deleted_at');

            return $q->when($filters['year']['value'] ?? null, fn($q, $year) => $q->where('year', $year))
                ->when($filters['assistant']['value'] ?? null, fn($q, $assistantId) => $q->whereHas('matterParties', fn($qp) => $qp->where('party_id', $assistantId)->where('role', 'expert')->where('type', 'assistant')))
                ->when($filters['court']['value'] ?? null, fn($q, $courtId) => $q->where('court_id', $courtId))
                ->when($filters['type']['value'] ?? null, fn($q, $typeId) => $q->where('type_id', $typeId))
                ->when($filters['collection_status']['values'] ?? null, fn($q, $status) => $q->whereIn('collection_status', $status));
        };

// 2. Base query for all unique months (using clean, un-scoped queries to prevent automatic soft-deleting interference)
        $monthsQuery = DB::table('matters')
            ->selectRaw("DATE_FORMAT(distributed_at, '%Y-%m') as year_month")
            ->whereNotNull('distributed_at')
            ->whereNull('deleted_at') // manual soft delete check
            ->tap($applyFilters)
            ->union(
                DB::table('matters')
                    ->selectRaw("DATE_FORMAT(initial_report_at, '%Y-%m') as year_month")
                    ->whereNotNull('initial_report_at')
                    ->whereNull('deleted_at')
                    ->tap($applyFilters)
            )
            ->union(
                DB::table('matters')
                    ->selectRaw("DATE_FORMAT(final_report_at, '%Y-%m') as year_month")
                    ->whereNotNull('final_report_at')
                    ->whereNull('deleted_at')
                    ->tap($applyFilters)
            );

        $months = DB::table(DB::raw("({$monthsQuery->toSql()}) as months"))
            ->mergeBindings($monthsQuery)
            ->select('year_month')
            ->distinct();

// 3. Construct subqueries for counts/sums
        $newMattersSub = Matter::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw("DATE_FORMAT(distributed_at, '%Y-%m') = months.year_month")
            ->tap($applyFilters);

        $initialReportsSub = Matter::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw("DATE_FORMAT(initial_report_at, '%Y-%m') = months.year_month")
            ->tap($applyFilters);

        $finalReportsSub = Matter::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw("DATE_FORMAT(final_report_at, '%Y-%m') = months.year_month")
            ->tap($applyFilters);

        $feesSub = DB::table('fees')
            ->join('matters', 'fees.matter_id', '=', 'matters.id')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereRaw("DATE_FORMAT(matters.distributed_at, '%Y-%m') = months.year_month")
            ->whereNull('matters.deleted_at') // Protect the join from deleted matters
            ->when($filters['year']['value'] ?? null, fn($q, $year) => $q->where('matters.year', $year))
            ->when($filters['assistant']['value'] ?? null, fn($q, $assistantId) => $q->whereExists(fn($qe) => $qe->select(DB::raw(1))->from('matter_party')->whereColumn('matter_party.matter_id', 'matters.id')->where('matter_party.party_id', $assistantId)->where('role', 'expert')->where('type', 'assistant')))
            ->when($filters['court']['value'] ?? null, fn($q, $courtId) => $q->where('matters.court_id', $courtId))
            ->when($filters['type']['value'] ?? null, fn($q, $typeId) => $q->where('matters.type_id', $typeId))
            ->when($filters['collection_status']['values'] ?? null, fn($q, $status) => $q->whereIn('matters.collection_status', $status));

// 4. Build master query out of DB::table to avoid model traits appending extra SQL logic
        $mainQuery = DB::table(DB::raw("({$months->toSql()}) as months"))
            ->mergeBindings($months)
            ->select('months.year_month')
            ->selectSub($newMattersSub, 'total_matters')
            ->selectSub($initialReportsSub, 'initial_reports')
            ->selectSub($finalReportsSub, 'final_reports')
            ->selectSub($feesSub, 'total_fees');

        if ($year = $filters['year']['value'] ?? null) {
            $mainQuery->whereRaw("months.year_month LIKE '{$year}-%'");
        }

        $mainQuery->orderBy('months.year_month', 'desc');

// 5. Hydrate it back to a clean Eloquent builder that Filament can paginate safely without breaking syntax
        return Matter::from(DB::raw("({$mainQuery->toSql()}) as report_table"))
            ->mergeBindings($mainQuery)
            ->withTrashed(); // 🌟 CRITICAL: This tells Eloquent "Do not append deleted_at to the outer query"
    }
}
