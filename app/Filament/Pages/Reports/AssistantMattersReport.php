<?php

namespace App\Filament\Pages\Reports;

use App\Enums\FeeType;
use App\Filament\Clusters\Reports;
use App\Filament\Exports\AssistantMattersExporter;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\Type;
use App\Support\ReportDateRangeFilter;
use App\Support\ReportPrintAction;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AssistantMattersReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.assistant-matters-report';

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $cluster = Reports::class;

    protected static ?int $navigationSort = 6;

    protected array $queryString = [
        'tableFilters',
        'tableSortColumn',
        'tableSortDirection',
        'tableSearch',
    ];

    public static function getNavigationLabel(): string
    {
        return __('Assistant Matters Report');
    }

    public function getTitle(): string
    {
        return __('Assistant Matters Report');
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->striped()
            ->extraAttributes(['class' => 'custom-compact-table [&_table]:text-xs'])
            ->paginated(false)
            ->columns([

                // ── Matter Reference ──────────────────────────────────────
                TextColumn::make('matter.reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn ($record) => $record->matter?->year.'/'.$record->matter?->number
                    )
                    ->weight(FontWeight::Bold)
                    ->url(fn ($record) => $record->matter_id
                        ? MatterResource::getUrl('view', ['record' => $record->matter_id])
                        : null
                    )
                    ->openUrlInNewTab()
                    ->width('7%'),

                // ── Assistant ─────────────────────────────────────────────
                TextColumn::make('party.name')
                    ->label(__('Assistant'))
                    ->weight(FontWeight::SemiBold)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('party', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    )
                    ->sortable(query: fn (Builder $query, string $direction) => $query->join('parties', 'parties.id', '=', 'matter_party.party_id')
                        ->orderBy('parties.name', $direction)
                    )
                    ->wrap()
                    ->width('10%'),

                // ── Court ─────────────────────────────────────────────────
                TextColumn::make('matter.court.name')
                    ->label(__('Court'))
                    ->wrap()
                    ->width('8%'),
                TextColumn::make('matter.difficulty')
                    ->label(__('Difficulty'))
                    ->badge(),

                // ── Matter Type ───────────────────────────────────────────
                TextColumn::make('matter.type.name')
                    ->label(__('Type'))
                    ->badge()
                    ->wrap()
                    ->width('8%'),
                // ── Status ────────────────────────────────────────────────
                TextColumn::make('matter.status')
                    ->label(__('Status'))
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->matter?->status)
                    ->width('7%'),

                // ── Experts on the matter ─────────────────────────────────
                TextColumn::make('matter.mainExpertsOnly.name')
                    ->label(__('Experts'))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->width('13%'),

                // ── Plaintiffs ────────────────────────────────────────────
                TextColumn::make('matter.mainPartiesOnly.party.name')
                    ->label(__('Parties'))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->width('13%'),

                TextColumn::make('matter.distributed_at')
                    ->label(__('Distributed At'))
                    ->date('d M Y')
                    ->placeholder(__('—'))
                    ->sortable()
                    ->width('7%'),
                // ── Initial Report Date ───────────────────────────────────
                TextColumn::make('matter.initial_report_at')
                    ->label(__('Initial Report'))
                    ->date('d M Y')
                    ->placeholder(__('—'))
                    ->sortable()
                    ->width('7%'),

                // ── Final Report Date ─────────────────────────────────────
                TextColumn::make('matter.final_report_at')
                    ->label(__('Final Report'))
                    ->date('d M Y')
                    ->placeholder(__('—'))
                    ->sortable()
                    ->width('7%'),

                // ── Total Fees (excl. VAT) ────────────────────────────────
                TextColumn::make('total_fees')
                    ->label(fn () => new HtmlString(__('Total Fees <br> (excl. VAT)')))
                    ->money('AED')
                    ->alignEnd()
                    ->width('7%'),

                // ── Total Allocations (excl. VAT) ─────────────────────────
                TextColumn::make('total_allocations')
                    ->label(fn () => new HtmlString(__('Total Collected <br> (excl. VAT)')))
                    ->money('AED')
                    ->alignEnd()
                    ->width('7%'),

                // ── Notes ────────────────────────────────────────────────
                TextColumn::make('matter.notes.text')
                    ->label(__('Notes'))
                    ->listWithLineBreaks()
                    ->placeholder(__('—'))
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->matter?->notes
                        ->map(fn ($note) => $note->text)
                        ->join(' | ')
                    )
                    ->width('12%'),

            ])
            ->filters([
                SelectFilter::make('party_id')
                    ->label(__('Assistant'))
                    ->options(fn () => Party::withRole('expert', 'assistant')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('experts')
                    ->label(__('Experts'))
                    ->options(fn () => Party::withRole('expert', 'certified')->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['values'] ?? $data['value'] ?? null,
                        function ($q, $expertIds) {
                            $ids = is_array($expertIds) ? array_filter($expertIds) : array_filter([$expertIds]);
                            if (empty($ids)) {
                                return $q;
                            }

                            return $q->whereHas('matter.parties', fn ($p) => $p->whereIn('parties.id', $ids));
                        }
                    ))
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('matter.type')
                    ->relationship('matter.type', 'name')
                    ->preload()
                    ->label(__('Matter Type'))
                    ->options(Type::pluck('name', 'id'))
                    ->searchable()
                    ->multiple(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'in_progress' => __('In Progress'),
                        'initial_report' => __('Initial Report'),
                        'final_report' => __('Final Report'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        // If nothing is selected, don't modify the query
                        if (empty($data['values'])) {
                            return $query;
                        }

                        return $query->where(function (Builder $subQuery) use ($data) {
                            foreach ($data['values'] as $value) {
                                $subQuery->orWhere(function (Builder $innerQuery) use ($value) {
                                    match ($value) {
                                        'in_progress' => $innerQuery->whereHas('matter', fn ($m) => $m->whereNull('final_report_at')->whereNull('initial_report_at')
                                        ),
                                        'initial_report' => $innerQuery->whereHas('matter', fn ($m) => $m->whereNull('final_report_at')->whereNotNull('initial_report_at')
                                        ),
                                        'final_report' => $innerQuery->whereHas('matter', fn ($m) => $m->whereNotNull('final_report_at')->whereNotNull('initial_report_at')
                                        ),
                                        default => $innerQuery,
                                    };
                                });
                            }
                        });
                    })
                    ->multiple(),
                ReportDateRangeFilter::make(
                    column: 'initial_report_at',
                    label: __('Initial Report Date'),
                    name: 'matter.initial_report_at',
                    applyUsing: fn (Builder $query, $from, $until) => $query
                        ->when($from, fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('initial_report_at', '>=', $from->toDateString())))
                        ->when($until, fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('initial_report_at', '<=', $until->toDateString()))),
                )->columnSpan(3),

                ReportDateRangeFilter::make(
                    column: 'final_report_at',
                    label: __('Final Report Date'),
                    name: 'matter.final_report_at',
                    applyUsing: fn (Builder $query, $from, $until) => $query
                        ->when($from, fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('final_report_at', '>=', $from->toDateString())))
                        ->when($until, fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('final_report_at', '<=', $until->toDateString()))),
                )->columnSpan(3),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->headerActions([
                ReportPrintAction::make(),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->exporter(AssistantMattersExporter::class)
                    ->label(__('Export'))
                    ->color('warning')
                    ->columnMappingColumns(3)
                    ->icon('heroicon-o-arrow-down-tray'),
            ]);
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    protected function getTableQuery(): Builder
    {
        return MatterParty::query()
            ->where('matter_party.role', 'expert')
            ->where('matter_party.type', 'assistant')
            ->withSum(['matter_fees as total_fees' => function ($q) {
                $q->where('type', '!=', FeeType::VAT->value);
            }], 'amount')
            ->withSum(['matter_allocations as total_allocations' => function ($q) {
                $q->whereHas('fee', function ($f) {
                    $f->where('type', '!=', FeeType::VAT->value);
                });
            }], 'amount')
            ->with([
                'party',
                'experts',
                'matter' => function ($query) {
                    $query->with(['court', 'type', 'notes']);
                },
            ])
            ->whereHas('matter') // Ensures we don't list assistants without a valid matter
            ->orderBy(
                Party::select('name')
                    ->whereColumn('parties.id', 'matter_party.party_id')
                    ->limit(1)
            );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function getAssistantOptions(): array
    {
        return Party::withRole('expert', 'assistant')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
