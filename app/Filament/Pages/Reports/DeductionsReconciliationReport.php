<?php

namespace App\Filament\Pages\Reports;

use App\Enums\FeeType;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Matter;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every matter carrying a deduction fee, and whether its money reconciles.
 *
 * This is the report the audit itself needed and did not have. It would have
 * surfaced, on day one: the 20 office-share fees stored with the wrong sign, and
 * the ~405 matters whose revenue line holds the gross payment while the
 * office-share line carries the offset.
 *
 * "Reconciles" means: revenue fees billed == net cash received across every one
 * of the matter's fee lines. That held to the cent across all 359 commission
 * matters when the audit checked it, so anything flagged here is genuinely worth
 * a look rather than an artefact of the recording convention.
 */
class DeductionsReconciliationReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.deductions-reconciliation-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Deductions Reconciliation');
    }

    public function getTitle(): string
    {
        return __('Deductions Reconciliation');
    }

    public function getTablePluralModelLabel(): string
    {
        return __('matters');
    }

    /**
     * Net billed on the matter: every fee line, signed.
     *
     * Kept as a shared expression because the filters have to repeat it. They
     * cannot filter on the `revenue_billed` alias: Filament runs a filter's
     * query closure inside a nested where group, and Laravel copies only the
     * wheres out of such a group, so a HAVING written there is dropped from the
     * SQL entirely — no error, no effect.
     */
    private function billedSql(): string
    {
        return '(SELECT COALESCE(SUM(f.amount), 0) FROM fees f
                    WHERE f.matter_id = matters.id)';
    }

    /** Everything collected across those same fee lines. */
    private function receivedSql(): string
    {
        return '(SELECT COALESCE(SUM(a.amount), 0) FROM allocations a
                    JOIN fees f ON f.id = a.fee_id
                    WHERE f.matter_id = matters.id)';
    }

    /** Deduction fees recorded positive: the legacy sign problem. */
    private function wrongSignedSql(string $placeholders): string
    {
        return '(SELECT COUNT(*) FROM fees f
                    WHERE f.matter_id = matters.id
                      AND f.type IN ('.$placeholders.')
                      AND f.amount > 0)';
    }

    private function deductionPlaceholders(): string
    {
        return implode(',', array_fill(0, count(FeeType::deductionTypeValues()), '?'));
    }

    protected function getTableQuery(): Builder
    {
        $deductions = FeeType::deductionTypeValues();

        $deductionPlaceholders = $this->deductionPlaceholders();

        return Matter::query()
            ->with(['court', 'type'])
            ->whereHas('fees', fn ($q) => $q->whereIn('type', $deductions))
            ->select('matters.*')
            // Every fee line, signed — the same set the allocations below span.
            // Comparing revenue-only billing against all-line collections made a
            // paid VAT line read as an unexplained variance.
            ->selectRaw($this->billedSql().' as revenue_billed')
            ->selectRaw(
                '(SELECT COALESCE(SUM(ABS(f.amount)), 0) FROM fees f
                    WHERE f.matter_id = matters.id
                      AND f.type IN ('.$deductionPlaceholders.')) as deductions_total',
                $deductions
            )
            ->selectRaw($this->receivedSql().' as cash_received')
            ->selectRaw(
                $this->wrongSignedSql($deductionPlaceholders).' as wrong_signed_count',
                $deductions
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('deductions_total', 'desc')
            ->emptyStateHeading(__('Nothing to reconcile'))
            ->emptyStateDescription(__('No matter matches these filters.'))
            ->emptyStateIcon('heroicon-o-check-circle')
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

                TextColumn::make('revenue_billed')
                    ->label(__('Net Billed'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('deductions_total')
                    ->label(__('Deductions'))
                    ->money('AED')
                    ->alignEnd()
                    ->color('warning')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('cash_received')
                    ->label(__('Net Cash Received'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('variance')
                    ->label(__('Variance'))
                    ->description(__('Billed minus received'))
                    ->getStateUsing(fn ($record) => (float) $record->revenue_billed - (float) $record->cash_received)
                    ->money('AED')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state) => abs((float) $state) < 0.005 ? 'success' : 'danger'),

                TextColumn::make('reconciles')
                    ->label(__('Reconciles'))
                    ->badge()
                    ->getStateUsing(fn ($record) => abs((float) $record->revenue_billed - (float) $record->cash_received) < 0.005
                        ? __('Balanced')
                        : __('Check'))
                    ->color(fn ($state) => $state === __('Balanced') ? 'success' : 'danger'),

                TextColumn::make('wrong_signed_count')
                    ->label(__('Wrong-signed Fees'))
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('unbalanced_only')
                    ->label(__('Not balanced only'))
                    ->query(fn (Builder $query) => $query->whereRaw(
                        'ABS('.$this->billedSql().' - '.$this->receivedSql().') >= 0.005'
                    )),

                Filter::make('wrong_signed_only')
                    ->label(__('Wrong-signed fees only'))
                    ->query(fn (Builder $query) => $query->whereRaw(
                        $this->wrongSignedSql($this->deductionPlaceholders()).' > 0',
                        FeeType::deductionTypeValues()
                    )),

                SelectFilter::make('deduction_type')
                    ->label(__('Deduction Type'))
                    ->options(fn () => collect(FeeType::cases())
                        ->filter(fn (FeeType $t) => $t->isNegative())
                        ->mapWithKeys(fn (FeeType $t) => [$t->value => $t->getLabel()])
                        ->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $type) => $q->whereHas('fees', fn ($f) => $f->where('type', $type))
                    )),

                Filter::make('billed_between')
                    ->label(__('Fee Date'))
                    ->schema([
                        Section::make(__('Fee Date'))->schema([
                            DatePicker::make('from')->label(__('From')),
                            DatePicker::make('until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereHas('fees', fn ($f) => $f->whereDate('date', '>=', $v)))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereHas('fees', fn ($f) => $f->whereDate('date', '<=', $v)))
                    ),
            ])
            ->filtersFormWidth(Width::ExtraLarge)
            ->queryStringIdentifier('deductions_reconciliation');
    }
}
