<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Models\Court;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Where the work comes from, and how much of it is still open.
 *
 * Matters could be filtered by court one at a time, but never compared across
 * courts — so "which court sends us the most work, and where is it piling up"
 * had no answer.
 */
class CourtWorkloadReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.court-workload-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Court Workload');
    }

    public function getTitle(): string
    {
        return __('Court Workload');
    }

    protected function getTableQuery(): Builder
    {
        $excluded = FeeType::excludedFromIncentiveValues();
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        return Court::query()
            ->withCount([
                'matters' => fn ($q) => $q->whereNull('matters.deleted_at'),
                'matters as open_matters_count' => fn ($q) => $q->whereNull('matters.deleted_at')
                    ->whereNull('matters.final_report_at'),
                'matters as awaiting_initial_count' => fn ($q) => $q->whereNull('matters.deleted_at')
                    ->whereNull('matters.initial_report_at'),
            ])
            ->selectRaw(
                '(SELECT COALESCE(SUM(f.amount), 0) FROM fees f
                    JOIN matters m ON m.id = f.matter_id
                    WHERE m.court_id = courts.id AND m.deleted_at IS NULL
                      AND (f.type IS NULL OR f.type NOT IN ('.$placeholders.'))) as revenue_billed',
                $excluded
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('matters_count', 'desc')
            ->emptyStateHeading(__('No courts'))
            ->emptyStateIcon('heroicon-o-building-library')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Court'))
                    ->weight('bold')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('matters_count')
                    ->label(__('Total Matters'))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('open_matters_count')
                    ->label(__('Still Open'))
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('awaiting_initial_count')
                    ->label(__('Awaiting Initial Report'))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('open_share')
                    ->label(__('Open Share'))
                    ->getStateUsing(fn ($record) => $record->matters_count > 0
                        ? round($record->open_matters_count / $record->matters_count * 100, 1).'%'
                        : '—')
                    ->badge()
                    ->color(function ($record) {
                        if ($record->matters_count === 0) {
                            return 'gray';
                        }

                        $share = $record->open_matters_count / $record->matters_count * 100;

                        return match (true) {
                            $share > 25 => 'danger',
                            $share > 10 => 'warning',
                            default => 'success',
                        };
                    })
                    ->alignEnd(),

                TextColumn::make('revenue_billed')
                    ->label(__('Revenue Billed'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('average_fee')
                    ->label(__('Average Fee'))
                    ->getStateUsing(fn ($record) => $record->matters_count > 0
                        ? (float) $record->revenue_billed / $record->matters_count
                        : 0)
                    ->money('AED')
                    ->alignEnd(),
            ])
            ->filters([
                Filter::make('with_matters')
                    ->label(__('With matters only'))
                    ->default()
                    ->query(fn (Builder $query) => $query->has('matters')),

                Filter::make('has_open')
                    ->label(__('With open matters only'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'matters',
                        fn ($q) => $q->whereNull('final_report_at')
                    )),
            ])
            ->filtersFormWidth(Width::Medium)
            ->queryStringIdentifier('court_workload');
    }
}
