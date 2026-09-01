<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Models\Matter;
use App\Services\IncentiveCalculatorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * An assistant's own incentive, one calculation at a time.
 *
 * The per-assistant statement already existed as a print view, but only as
 * something a manager opened FOR someone: its route takes an arbitrary party id
 * and checked nothing beyond being logged in, so any authenticated user could
 * read anyone's payroll. This is the assistant's own door to the same figures.
 *
 * Scoped by the signed-in user's linked Party and fails CLOSED — no Party link,
 * no page, and the query is pinned to that party rather than filtered by it, so
 * there is no filter to clear or URL to edit that widens it.
 *
 * FINALIZED calculations only. A draft is still being recalculated — its
 * numbers move as matters are imported and percentages adjusted — and showing
 * an assistant a figure that will change is worse than showing nothing.
 */
class MyIncentiveReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports.my-incentive-report';

    /**
     * Per-matter fee and base totals for the calculation on screen.
     *
     * A matter with several fees gets one IncentiveLine per fee but only ONE
     * assistant line, so the line's own fee_amount_excl_vat is just the first
     * fee while the share already covers them all.
     *
     * @var Collection<int, object>|null
     */
    private ?Collection $matterTotals = null;

    /** @var Collection<int, int>|null */
    private ?Collection $assistantCounts = null;

    public static function getNavigationLabel(): string
    {
        return __('My Incentive');
    }

    public function getTitle(): string
    {
        return __('My Incentive');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    /**
     * Anyone with a Party link may read their own statement. No extra permission
     * is required precisely because the page can only ever show the viewer's own
     * figures.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->party !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    private function partyId(): ?int
    {
        return auth()->user()?->party?->id;
    }

    /**
     * Finalized calculations this assistant actually appears in, newest first.
     *
     * @return Collection<int, IncentiveCalculation>
     */
    public function availableCalculations(): Collection
    {
        $partyId = $this->partyId();

        if (! $partyId) {
            return collect();
        }

        return IncentiveCalculation::query()
            ->where('status', 'finalized')
            ->whereHas('lines.assistantLines', fn (Builder $q) => $q->where('party_id', $partyId))
            ->orderByDesc('period_end')
            ->get();
    }

    public function selectedCalculationId(): ?int
    {
        $chosen = $this->tableFilters['calculation']['value'] ?? null;

        if ($chosen) {
            return (int) $chosen;
        }

        return $this->availableCalculations()->first()?->id;
    }

    public function selectedCalculation(): ?IncentiveCalculation
    {
        $id = $this->selectedCalculationId();

        return $id ? $this->availableCalculations()->firstWhere('id', $id) : null;
    }

    /**
     * The assistant's period totals — bonus, penalty and fixed deduction are
     * period-level, not per matter, so they cannot be summed from the table.
     */
    public function periodTotals(): ?IncentiveAssistantExtra
    {
        $id = $this->selectedCalculationId();

        if (! $id || ! $this->partyId()) {
            return null;
        }

        return IncentiveAssistantExtra::query()
            ->where('incentive_calculation_id', $id)
            ->where('party_id', $this->partyId())
            ->first();
    }

    public function shareTotal(): float
    {
        return (float) $this->getTableQuery()->sum('share_amount');
    }

    public function netTotal(): float
    {
        $extra = $this->periodTotals();

        return max(0.0, (float) $this->getTableQuery()->sum('total_amount') - (float) ($extra?->fixed_deduction ?? 0));
    }

    /**
     * @return Collection<int, object>
     */
    private function matterTotals(): Collection
    {
        return $this->matterTotals ??= IncentiveLine::query()
            ->where('incentive_calculation_id', $this->selectedCalculationId())
            ->selectRaw('matter_id, SUM(fee_amount_excl_vat) as total_fee_amount, SUM(base_amount) as total_base_amount')
            ->groupBy('matter_id')
            ->get()
            ->keyBy('matter_id');
    }

    /**
     * How many assistants share each matter in this calculation.
     *
     * @return Collection<int, int>
     */
    private function assistantCounts(): Collection
    {
        return $this->assistantCounts ??= IncentiveAssistantLine::query()
            ->whereHas('incentiveLine', fn (Builder $q) => $q->where('incentive_calculation_id', $this->selectedCalculationId()))
            ->with('incentiveLine:id,matter_id')
            ->get(['id', 'incentive_line_id', 'party_id'])
            ->groupBy('incentiveLine.matter_id')
            ->map(fn (Collection $rows) => $rows->pluck('party_id')->unique()->count());
    }

    /**
     * The matter behind an assistant line.
     *
     * IncentiveLine::matter() carries no generic annotation, so the relation
     * reads as a bare Model; resolving it once here keeps the columns out of
     * every closure below.
     */
    private function matterOf(IncentiveAssistantLine $record): ?Matter
    {
        $matter = $record->incentiveLine?->getAttribute('matter');

        return $matter instanceof Matter ? $matter : null;
    }

    private function feeTotalFor(IncentiveAssistantLine $record): float
    {
        $line = $record->incentiveLine;

        return (float) ($this->matterTotals()->get($line?->getAttribute('matter_id'))?->total_fee_amount
            ?? $line?->getAttribute('fee_amount_excl_vat')
            ?? 0);
    }

    /**
     * The two facts the bare rate cannot convey: that the matter was shared, and
     * what this assistant's cut of the fee actually came to.
     */
    public function describeRate(IncentiveAssistantLine $record): ?string
    {
        $parts = [];

        if ($record->percentage_override !== null) {
            $parts[] = __('override');
        }

        $count = (int) $this->assistantCounts()->get($record->incentiveLine?->getAttribute('matter_id'), 1);

        if ($count > 1) {
            $parts[] = __('Split between :count assistants', ['count' => $count]);
        }

        $fee = $this->feeTotalFor($record);

        if ($fee > 0) {
            $own = round((float) $record->share_amount / $fee * 100, 2);

            if ($own != (float) $record->incentiveLine?->getAttribute('effective_percentage')) {
                $parts[] = __('Your share').': '.$own.'%';
            }
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    protected function getTableQuery(): Builder
    {
        $partyId = $this->partyId();
        $calculationId = $this->selectedCalculationId();

        $query = IncentiveAssistantLine::query()
            ->with(['incentiveLine.matter.court', 'incentiveLine.matter.type', 'incentiveLine.deductions']);

        // Fails closed: no party link, or no finalized calculation to show, and
        // the page returns nothing rather than everything.
        if (! $partyId || ! $calculationId) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('party_id', $partyId)
            ->whereHas('incentiveLine', fn (Builder $q) => $q->where('incentive_calculation_id', $calculationId));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('share_amount', 'desc')
            ->emptyStateHeading(__('Nothing to show yet'))
            ->emptyStateDescription(__('You have no matters in a finalized incentive calculation.'))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                TextColumn::make('matter_reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn (IncentiveAssistantLine $record) => $this->matterOf($record)?->reference ?? '—')
                    ->url(fn (IncentiveAssistantLine $record) => ($matter = $this->matterOf($record))
                        ? MatterResource::getUrl('view', ['record' => $matter])
                        : null)
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('case_info')
                    ->label(__('Court / Type'))
                    // getAttribute again: Matter::type() and ::court() carry no
                    // generic annotation either, so both read as a bare Model.
                    ->getStateUsing(fn (IncentiveAssistantLine $record) => $this->matterOf($record)?->type?->getAttribute('name'))
                    ->description(fn (IncentiveAssistantLine $record) => $this->matterOf($record)?->court?->getAttribute('name'))
                    ->placeholder('—')
                    ->wrap(),

                TextColumn::make('incentiveLine.completion_days')
                    ->label(__('Days'))
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('fee_amount')
                    ->label(__('Fee'))
                    ->getStateUsing(fn (IncentiveAssistantLine $record) => $this->feeTotalFor($record))
                    ->money('AED')
                    ->alignEnd(),

                TextColumn::make('incentiveLine.effective_percentage')
                    ->label(__('Rate %'))
                    ->suffix('%')
                    ->description(fn (IncentiveAssistantLine $record) => $this->describeRate($record))
                    ->alignEnd(),

                TextColumn::make('incentiveLine.total_deduction_pct')
                    ->label(__('Deductions'))
                    ->suffix('%')
                    ->color('danger')
                    ->placeholder('0%')
                    ->description(fn (IncentiveAssistantLine $record) => collect($record->incentiveLine?->getAttribute('deductions'))
                        ->map(fn ($d) => '−'.$d->percentage.'% ('.__($d->type).')')
                        ->implode(' · ') ?: null)
                    ->alignEnd(),

                TextColumn::make('share_amount')
                    ->label(__('Share'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('extra_amount')
                    ->label(__('Extra'))
                    ->money('AED')
                    ->color('success')
                    ->placeholder('—')
                    ->description(fn (IncentiveAssistantLine $record) => app(IncentiveCalculatorService::class)
                        ->describeExtraReason((float) $record->extra_percentage))
                    ->alignEnd(),

                TextColumn::make('minimum_penalty_amount')
                    ->label(__('Penalty'))
                    ->money('AED')
                    ->color('danger')
                    ->placeholder('—')
                    ->description(fn (IncentiveAssistantLine $record) => app(IncentiveCalculatorService::class)
                        ->describePenaltyReason((float) $record->minimum_penalty_pct))
                    ->alignEnd(),

                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->money('AED')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),
            ])
            ->filters([
                SelectFilter::make('calculation')
                    ->label(__('Calculation'))
                    ->options(fn () => $this->availableCalculations()
                        ->mapWithKeys(fn (IncentiveCalculation $c) => [$c->id => $c->name])
                        ->all())
                    ->default(fn () => $this->availableCalculations()->first()?->id)
                    // The calculation is applied in getTableQuery(), which needs
                    // it to build the per-matter totals as well; this closure
                    // only has to stop Filament applying its own `calculation`
                    // column filter on top.
                    ->query(fn (Builder $query) => $query),
            ])
            ->filtersFormWidth(Width::Medium)
            ->headerActions([
                Action::make('print')
                    ->label(__('Print my statement'))
                    ->icon('heroicon-o-printer')
                    ->url(fn () => $this->selectedCalculationId()
                        ? route('incentive.calculation.print.assistant', [
                            'calculation' => $this->selectedCalculationId(),
                            'party' => $this->partyId(),
                        ])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn () => $this->selectedCalculationId() !== null),
            ])
            ->queryStringIdentifier('my_incentive');
    }
}
