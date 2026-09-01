<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Models\Type;
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
 * Which categories of work actually pay.
 *
 * Revenue per matter type set against the incentive actually paid out on it, so
 * the office can see the margin rather than only the turnover. Nothing joined
 * those two sides before: fee totals lived in the matter reports and incentive
 * totals lived inside a calculation, and the two were never put side by side.
 *
 * Incentive cost counts FINALIZED calculations only — draft figures still move.
 */
class TypeProfitabilityReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.type-profitability-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Profitability by Matter Type');
    }

    public function getTitle(): string
    {
        return __('Profitability by Matter Type');
    }

    protected function getTableQuery(): Builder
    {
        $excluded = FeeType::excludedFromIncentiveValues();
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        return Type::query()
            ->withCount(['matters' => fn ($q) => $q->whereNull('matters.deleted_at')])
            // Revenue billed: the matter's own fees, VAT and deduction types out.
            ->selectRaw(
                '(SELECT COALESCE(SUM(f.amount), 0) FROM fees f
                    JOIN matters m ON m.id = f.matter_id
                    WHERE m.type_id = types.id AND m.deleted_at IS NULL
                      AND (f.type IS NULL OR f.type NOT IN ('.$placeholders.'))) as revenue_billed',
                $excluded
            )
            // Cash actually received, netted across every line on those matters.
            ->selectRaw(
                '(SELECT COALESCE(SUM(a.amount), 0) FROM allocations a
                    JOIN fees f ON f.id = a.fee_id
                    JOIN matters m ON m.id = f.matter_id
                    WHERE m.type_id = types.id AND m.deleted_at IS NULL) as revenue_collected'
            )
            // Incentive paid out, finalized calculations only.
            ->selectRaw(
                "(SELECT COALESCE(SUM(ial.total_amount), 0)
                    FROM incentive_assistant_lines ial
                    JOIN incentive_lines il ON il.id = ial.incentive_line_id
                    JOIN incentive_calculations ic ON ic.id = il.incentive_calculation_id
                    JOIN matters m ON m.id = il.matter_id
                    WHERE m.type_id = types.id AND ic.status = 'finalized') as incentive_paid"
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('revenue_billed', 'desc')
            ->emptyStateHeading(__('No matter types'))
            ->emptyStateIcon('heroicon-o-presentation-chart-line')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Matter Type'))
                    ->weight('bold')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('matters_count')
                    ->label(__('Matters'))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('revenue_billed')
                    ->label(__('Revenue Billed'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('revenue_collected')
                    ->label(__('Collected'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('incentive_paid')
                    ->label(__('Incentive Paid'))
                    ->description(__('Finalized calculations only'))
                    ->money('AED')
                    ->alignEnd()
                    ->color('warning')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('net_to_office')
                    ->label(__('Net to Office'))
                    ->getStateUsing(fn ($record) => (float) $record->revenue_billed - (float) $record->incentive_paid)
                    ->money('AED')
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('incentive_ratio')
                    ->label(__('Incentive as % of Revenue'))
                    ->getStateUsing(function ($record) {
                        $revenue = (float) $record->revenue_billed;

                        return $revenue > 0.005
                            ? round((float) $record->incentive_paid / $revenue * 100, 1).'%'
                            : '—';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $revenue = (float) $record->revenue_billed;

                        if ($revenue <= 0.005) {
                            return 'gray';
                        }

                        $ratio = (float) $record->incentive_paid / $revenue * 100;

                        return match (true) {
                            $ratio > 25 => 'danger',
                            $ratio > 15 => 'warning',
                            default => 'success',
                        };
                    })
                    ->alignEnd(),

                TextColumn::make('average_fee')
                    ->label(__('Average Fee'))
                    ->getStateUsing(fn ($record) => $record->matters_count > 0
                        ? (float) $record->revenue_billed / $record->matters_count
                        : 0)
                    ->money('AED')
                    ->alignEnd(),
            ])
            ->filters([
                Filter::make('with_activity')
                    ->label(__('With matters only'))
                    ->default()
                    ->query(fn (Builder $query) => $query->has('matters')),

                Filter::make('active_only')
                    ->label(__('Active types only'))
                    ->query(fn (Builder $query) => $query->where('active', true)),
            ])
            ->filtersFormWidth(Width::Medium)
            ->queryStringIdentifier('type_profitability');
    }
}
