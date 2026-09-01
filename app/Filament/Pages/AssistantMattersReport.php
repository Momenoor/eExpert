<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Filament\Exports\AssistantMattersExporter;
use App\Filament\Resources\Matters\MatterResource;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Models\Type;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AssistantMattersReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected string $view = 'filament.pages.assistant-matters-report';

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|null|UnitEnum $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected array $queryString = [
        'tableFilters',
        'tableSortColumn',
        'tableSortDirection',
        'tableSearch',
    ];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(parent::getNavigationGroup());
    }

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
            ->defaultPaginationPageOption(25)
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
                SelectFilter::make('party.name')
                    ->relationship('party', 'name', fn ($query) => $query->withRole('expert', 'assistant'))
                    ->label(__('Assistant'))
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('experts')
                    ->relationship('matter.mainExpertsOnly', 'name')
                    ->label(__('Experts'))
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
                Filter::make('matter.initial_report_at')
                    ->indicator('initial_report_at')
                    ->label(__('Initial Report Date'))
                    ->schema([
                        Section::make(__('Initial Report Date'))->schema([
                            DatePicker::make('initial_from')->label(__('From')),
                            DatePicker::make('initial_until')->label(__('Until')),
                        ])->columnSpanFull(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query
                            ->when($data['initial_from'], fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('initial_report_at', '>=', $data['initial_from'])
                            )
                            )
                            ->when($data['initial_until'], fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('initial_report_at', '<=', $data['initial_until'])
                            )
                            );
                    })
                    ->indicator('initial_report_at')
                    ->columns(2),

                Filter::make('matter.final_report_at')
                    ->indicator('final_report_at')
                    ->label(__('Final Report Date'))
                    ->schema([
                        Section::make(__('Final Report Date'))->schema([
                            DatePicker::make('final_from')->label(__('From')),
                            DatePicker::make('final_until')->label(__('Until')),
                        ])->columnSpanFull(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query
                            ->when($data['final_from'], fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('final_report_at', '>=', $data['final_from'])
                            )
                            )
                            ->when($data['final_until'], fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('final_report_at', '<=', $data['final_until'])
                            )
                            );
                    })
                    ->columns(2),

            ])
            ->filtersFormColumns(2)
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
