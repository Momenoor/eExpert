<?php

namespace App\Filament\Pages\Reports;

use App\Enums\FeeType;
use App\Filament\Clusters\Reports;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Fee;
use App\Support\ReportDateRangeFilter;
use App\Support\ReportPrintAction;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * VAT charged and collected, per fee, for a filing period.
 *
 * VAT is recorded as its own fee line and is deliberately excluded from every
 * incentive and revenue figure in the app — which meant nobody could see it at
 * all. This is the one place it is the subject rather than the exclusion.
 *
 * Listed per fee rather than per month so a return can be tied back to the
 * individual matter it came from; the period filter and column totals give the
 * figure to file.
 */
class VatSummaryReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $cluster = Reports::class;

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.vat-summary-report';

    public static function getNavigationLabel(): string
    {
        return __('VAT Summary');
    }

    public function getTitle(): string
    {
        return __('VAT Summary');
    }

    public function getTablePluralModelLabel(): string
    {
        return __('fees');
    }

    protected function getTableQuery(): Builder
    {
        return Fee::query()
            ->where('fees.type', FeeType::VAT->value)
            ->whereHas('matter')
            ->with(['matter.court', 'matter.type'])
            ->withSum('allocations as vat_collected', 'amount');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->paginated(false)
            ->defaultSort('date', 'desc')
            ->emptyStateHeading(__('No VAT recorded'))
            ->emptyStateDescription(__('No VAT fee falls in this period.'))
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('matter.reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn (Fee $record) => $record->matter
                        ? $record->matter->year.'/'.$record->matter->number
                        : '—')
                    ->url(fn (Fee $record) => $record->matter
                        ? MatterResource::getUrl('view', ['record' => $record->matter])
                        : null)
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'matter',
                        fn ($m) => $m->where('number', 'like', "%{$search}%")
                            ->orWhere('year', 'like', "%{$search}%")
                    )),

                TextColumn::make('matter.court.name')
                    ->label(__('Court / Type'))
                    ->description(fn (Fee $record) => $record->matter?->type?->name)
                    ->wrap(),

                TextColumn::make('amount')
                    ->label(__('VAT Charged'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('vat_collected')
                    ->label(__('VAT Collected'))
                    ->getStateUsing(fn (Fee $record) => (float) ($record->vat_collected ?? 0))
                    ->money('AED')
                    ->alignEnd()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('vat_outstanding')
                    ->label(__('Not Yet Collected'))
                    ->getStateUsing(fn (Fee $record) => (float) $record->amount - (float) ($record->vat_collected ?? 0))
                    ->money('AED')
                    ->alignEnd()
                    ->color(fn ($state) => (float) $state > 0.005 ? 'warning' : 'success'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->filters([
                ReportDateRangeFilter::make(
                    column: 'fees.date',
                    label: __('Period'),
                    name: 'period',
                )->columnSpan(3),

                Filter::make('uncollected_only')
                    ->label(__('Not fully collected'))
                    ->query(fn (Builder $query) => $query->whereRaw(
                        'fees.amount > COALESCE((SELECT SUM(a.amount) FROM allocations a WHERE a.fee_id = fees.id), 0) + 0.005'
                    )),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormWidth(Width::ExtraLarge)
            ->headerActions([
                ReportPrintAction::make(),
            ])
            ->queryStringIdentifier('vat_summary');
    }
}
