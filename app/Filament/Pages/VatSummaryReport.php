<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Fee;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.vat-summary-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('VAT Summary');
    }

    public function getTitle(): string
    {
        return __('VAT Summary');
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
            ->query($this->getTableQuery())
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
                Filter::make('period')
                    ->label(__('Period'))
                    ->schema([
                        Section::make(__('Period'))->schema([
                            DatePicker::make('from')->label(__('From')),
                            DatePicker::make('until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('fees.date', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('fees.date', '<=', $v))
                    )
                    ->indicateUsing(function (array $data) {
                        if (! ($data['from'] ?? null) && ! ($data['until'] ?? null)) {
                            return null;
                        }

                        return __('Period').': '.($data['from'] ?? '…').' → '.($data['until'] ?? '…');
                    }),

                Filter::make('uncollected_only')
                    ->label(__('Not fully collected'))
                    ->query(fn (Builder $query) => $query->whereRaw(
                        'fees.amount > COALESCE((SELECT SUM(a.amount) FROM allocations a WHERE a.fee_id = fees.id), 0) + 0.005'
                    ))
                    ->indicateUsing(fn () => __('Not fully collected')),
            ])
            ->filtersFormWidth(Width::ExtraLarge)
            ->queryStringIdentifier('vat_summary');
    }
}
