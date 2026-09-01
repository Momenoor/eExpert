<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Models\Party;
use App\Support\Sql;
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
 * One row per assistant: workload, output, earnings and availability.
 *
 * The pieces existed in separate places — matter counts in one report, fee
 * totals in another, incentive inside a calculation, leave only on a calendar —
 * so nobody could see an assistant's whole picture at once. This joins them.
 *
 * Incentive counts FINALIZED calculations only, since draft figures still move.
 * Leave days are shown because they legitimately reduce what an assistant could
 * have taken on, and the incentive engine already prorates monthly targets by
 * exactly that ratio.
 */
class AssistantPerformanceReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.assistant-performance-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Assistant Performance');
    }

    public function getTitle(): string
    {
        return __('Assistant Performance');
    }

    protected function getTableQuery(): Builder
    {
        $excluded = FeeType::excludedFromIncentiveValues();
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        // matter_party rows for this party acting as an assistant.
        $asAssistant = "SELECT mp.matter_id FROM matter_party mp
            WHERE mp.party_id = parties.id AND mp.role = 'expert' AND mp.type = 'assistant'";

        return Party::query()
            ->withRole('expert', 'assistant')
            ->select('parties.*')
            ->selectRaw(
                "(SELECT COUNT(DISTINCT m.id) FROM matters m
                    WHERE m.id IN ({$asAssistant}) AND m.deleted_at IS NULL) as matters_total"
            )
            ->selectRaw(
                "(SELECT COUNT(DISTINCT m.id) FROM matters m
                    WHERE m.id IN ({$asAssistant}) AND m.deleted_at IS NULL
                      AND m.final_report_at IS NULL) as matters_open"
            )
            ->selectRaw(
                "(SELECT COUNT(DISTINCT m.id) FROM matters m
                    WHERE m.id IN ({$asAssistant}) AND m.deleted_at IS NULL
                      AND m.final_report_at IS NOT NULL) as matters_completed"
            )
            ->selectRaw(
                "(SELECT COALESCE(SUM(f.amount), 0) FROM fees f
                    JOIN matters m ON m.id = f.matter_id
                    WHERE m.id IN ({$asAssistant}) AND m.deleted_at IS NULL
                      AND (f.type IS NULL OR f.type NOT IN ({$placeholders}))) as fees_handled",
                $excluded
            )
            ->selectRaw(
                "(SELECT COALESCE(SUM(ial.total_amount), 0)
                    FROM incentive_assistant_lines ial
                    JOIN incentive_lines il ON il.id = ial.incentive_line_id
                    JOIN incentive_calculations ic ON ic.id = il.incentive_calculation_id
                    WHERE ial.party_id = parties.id AND ic.status = 'finalized') as incentive_earned"
            )
            ->selectRaw(
                '(SELECT COALESCE(SUM('.Sql::daysBetween('pl.start_date', 'pl.end_date').' + 1), 0)
                    FROM party_leaves pl WHERE pl.party_id = parties.id) as leave_days'
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('matters_total', 'desc')
            ->emptyStateHeading(__('No assistants'))
            ->emptyStateIcon('heroicon-o-user-group')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Assistant'))
                    ->weight('bold')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('matters_total')
                    ->label(__('Matters'))
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('matters_completed')
                    ->label(__('Completed'))
                    ->alignEnd()
                    ->color('success')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('matters_open')
                    ->label(__('Still Open'))
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),

                TextColumn::make('completion_rate')
                    ->label(__('Completion Rate'))
                    ->getStateUsing(fn ($record) => (int) $record->matters_total > 0
                        ? round((int) $record->matters_completed / (int) $record->matters_total * 100, 1).'%'
                        : '—')
                    ->badge()
                    ->color(function ($record) {
                        if ((int) $record->matters_total === 0) {
                            return 'gray';
                        }

                        $rate = (int) $record->matters_completed / (int) $record->matters_total * 100;

                        return match (true) {
                            $rate >= 90 => 'success',
                            $rate >= 70 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->alignEnd(),

                TextColumn::make('fees_handled')
                    ->label(__('Fees Handled'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('incentive_earned')
                    ->label(__('Incentive Earned'))
                    ->description(__('Finalized calculations only'))
                    ->money('AED')
                    ->alignEnd()
                    ->color('success')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('leave_days')
                    ->label(__('Leave Days'))
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('with_matters')
                    ->label(__('With matters only'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereHas(
                        'matters',
                        fn ($q) => $q->where('matter_party.role', 'expert')->where('matter_party.type', 'assistant')
                    )),

                // An existence check, not `havingRaw('matters_open > 0')`.
                // Filament runs this closure inside a nested where group and
                // Laravel copies only the wheres out of such a group, so a
                // HAVING here never reaches the SQL. This also avoids counting
                // rows we only need to know exist.
                Filter::make('has_open')
                    ->label(__('With open matters only'))
                    ->query(fn (Builder $query) => $query->whereHas(
                        'matters',
                        fn ($q) => $q->where('matter_party.role', 'expert')
                            ->where('matter_party.type', 'assistant')
                            ->whereNull('matters.final_report_at')
                    )),
            ])
            ->filtersFormWidth(Width::Medium)
            ->queryStringIdentifier('assistant_performance');
    }
}
