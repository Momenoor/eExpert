<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\Court;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Type;
use App\Support\Sql;
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
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * What is owed, what came in, what is still outstanding, and how overdue it is.
 *
 * Nothing in the system computed aging before this — collection_status only ever
 * said paid / partial / unpaid, never "how late".
 *
 * Computed per MATTER, not per fee. On the historical commission matters the
 * client's gross payment was allocated to the revenue fee while the office-share
 * line carried the offsetting negative, so a per-fee view shows the revenue line
 * as over-collected and the deduction line as uncollected. Netting at matter
 * level makes those cancel, which is what actually happened:
 *
 *   net billed  = SUM of every fee on the matter, signed — VAT adds, deduction
 *                 lines subtract
 *   received    = SUM of every allocation across those same fee lines
 *   outstanding = net billed - received
 *
 * Both sides must span the same fee lines. Counting only revenue fees as owed
 * while counting all allocations as received made every paid VAT or office-share
 * line look like over-collection of exactly its own amount.
 *
 * A matter is aged from its oldest fee, i.e. when the office first became owed
 * money on it.
 */
class FeeCollectionAgingReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.fee-collection-aging-report';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

    public static function getNavigationLabel(): string
    {
        return __('Fee Collection & Aging');
    }

    public function getTitle(): string
    {
        return __('Fee Collection & Aging');
    }

    public function getTablePluralModelLabel(): string
    {
        return __('fees');
    }

    protected function getTableQuery(): Builder
    {
        // Billed and received must cover the SAME set of fee lines, or the two
        // sides cannot be compared. An earlier version counted only revenue
        // fees as owed while counting allocations from every line as received,
        // so a paid VAT line — or a settled office share — surfaced as
        // over-collection equal to its own amount, on 55 matters.
        //
        // Both sides now span every fee on the matter and keep their signs, so
        // VAT adds to what is owed and deduction lines subtract from it, and a
        // fully settled matter lands on exactly zero.
        $owed = DB::table('fees')
            ->selectRaw('matter_id, SUM(amount) as owed, MIN(date) as first_billed')
            ->groupBy('matter_id');

        $received = DB::table('allocations')
            ->join('fees', 'fees.id', '=', 'allocations.fee_id')
            ->selectRaw('fees.matter_id as matter_id, SUM(allocations.amount) as received')
            ->groupBy('fees.matter_id');

        return Matter::query()
            ->joinSub($owed, 'billed', 'billed.matter_id', '=', 'matters.id')
            ->leftJoinSub($received, 'paid', 'paid.matter_id', '=', 'matters.id')
            ->with(['court', 'type', 'assistantsOnly.party'])
            ->select('matters.*')
            ->selectRaw('billed.owed as owed_amount')
            ->selectRaw('billed.first_billed as first_billed')
            ->selectRaw('COALESCE(paid.received, 0) as received_amount')
            ->selectRaw('(billed.owed - COALESCE(paid.received, 0)) as outstanding_amount')
            ->selectRaw(Sql::daysSince('billed.first_billed').' as days_outstanding');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->defaultSort('outstanding_amount', 'desc')
            ->emptyStateHeading(__('Nothing outstanding'))
            ->emptyStateDescription(__('No matter matches these filters with a balance owing.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn (Matter $record) => $record->year.'/'.$record->number)
                    ->url(fn (Matter $record) => MatterResource::getUrl('view', ['record' => $record]))
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn ($q) => $q->where('matters.number', 'like', "%{$search}%")
                            ->orWhere('matters.year', 'like', "%{$search}%")
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

                TextColumn::make('first_billed')
                    ->label(__('First Billed'))
                    ->date()
                    ->sortable(),

                TextColumn::make('days_outstanding')
                    ->label(__('Age'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __(':days days', ['days' => (int) $state]))
                    ->color(fn ($state) => match (true) {
                        (int) $state > 90 => 'danger',
                        (int) $state > 60 => 'warning',
                        (int) $state > 30 => 'info',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('owed_amount')
                    ->label(__('Net Billed'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('received_amount')
                    ->label(__('Collected'))
                    ->money('AED')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),

                TextColumn::make('outstanding_amount')
                    ->label(__('Outstanding'))
                    ->money('AED')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state) => (float) $state > 0.005 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))->money('AED')),
            ])
            ->filters([
                // WHERE, not HAVING. Filament runs a filter's query closure
                // inside a nested where group, and Laravel copies only the
                // wheres out of such a group — a having written here is
                // dropped from the SQL entirely, so the filter compiles, raises
                // no error and changes nothing. The joined subqueries expose
                // `billed` and `paid` as real columns, so a plain WHERE on the
                // underlying expression does the job.
                Filter::make('outstanding_only')
                    ->label(__('Outstanding only'))
                    ->default()
                    ->query(fn (Builder $query) => $query->whereRaw(
                        '(billed.owed - COALESCE(paid.received, 0)) > 0.005'
                    )),

                SelectFilter::make('aging_bucket')
                    ->label(__('Age'))
                    ->options([
                        '0-30' => __('0-30 days'),
                        '31-60' => __('31-60 days'),
                        '61-90' => __('61-90 days'),
                        '90+' => __('90+ days'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $age = Sql::daysSince('billed.first_billed');

                        return match ($data['value'] ?? null) {
                            '0-30' => $query->whereRaw($age.' <= 30'),
                            '31-60' => $query->whereRaw($age.' BETWEEN 31 AND 60'),
                            '61-90' => $query->whereRaw($age.' BETWEEN 61 AND 90'),
                            '90+' => $query->whereRaw($age.' > 90'),
                            default => $query,
                        };
                    }),

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
                        fn ($q, $partyId) => $q->whereHas(
                            'assistantsOnly',
                            fn ($a) => $a->where('party_id', $partyId)
                        )
                    )),

                Filter::make('billed_between')
                    ->label(__('First Billed'))
                    ->schema([
                        Section::make(__('First Billed'))->schema([
                            DatePicker::make('billed_from')->label(__('From')),
                            DatePicker::make('billed_until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['billed_from'] ?? null, fn ($q, $v) => $q->whereDate('billed.first_billed', '>=', $v))
                        ->when($data['billed_until'] ?? null, fn ($q, $v) => $q->whereDate('billed.first_billed', '<=', $v))
                    ),
            ])
            ->filtersFormWidth(Width::ExtraLarge)
            ->persistSearchInSession()
            ->queryStringIdentifier('fee_aging');
    }
}
