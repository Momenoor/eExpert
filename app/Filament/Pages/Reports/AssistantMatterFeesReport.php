<?php

namespace App\Filament\Pages\Reports;

use App\Enums\FeeType;
use App\Filament\Clusters\Reports;
use App\Filament\Exports\AssistantMatterFeesExporter;
use App\Models\MatterParty;
use App\Models\Party;
use App\Support\ReportDateRangeFilter;
use App\Support\ReportPrintAction;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssistantMatterFeesReport extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $cluster = Reports::class;

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.assistant-matter-fees-report';

    protected static ?string $navigationLabel = 'Assistant Fees Report';

    public static function getNavigationLabel(): string
    {
        return __('Assistant Fees Report');
    }

    public function getTitle(): string
    {
        return __('Assistant Fees Report');
    }

    public function table(Table $table): Table
    {

        return $table
            ->query(fn () => $this->getTableQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('matter.reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn ($record) => $record->matter->year.'/'.$record->matter->number)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('matter', function ($q) use ($search) {
                            $q->where('year', 'like', "%{$search}%")
                                ->orWhere('number', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('matter.court.name')
                    ->label(__('Court'))
                    ->sortable(),
                TextColumn::make('matter.type.name')
                    ->label(__('Type'))
                    ->sortable(),
                TextColumn::make('party.name')
                    ->label(__('Assistant'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('matter.final_report_at')
                    ->label(__('Final Report Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('total_matter_fees')
                    ->label(__('Total Matter Fees'))
                    ->money('AED')
                    ->alignEnd(),
                TextColumn::make('divided_fees')
                    ->label(__('Divided Fees'))
                    ->getStateUsing(fn ($record) => ($record->assistants_count > 0) ? ($record->total_matter_fees / $record->assistants_count) : 0)
                    ->money('AED')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('party_id')
                    ->label(__('Assistant'))
                    ->multiple()
                    ->preload()
                    ->options(Party::withRole('expert', 'assistant')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                    )
                    ->searchable(),
                ReportDateRangeFilter::make(
                    column: 'final_report_at',
                    label: __('Final Report Date'),
                    name: 'final_report_at',
                    applyUsing: fn (Builder $query, $from, $until) => $query
                        ->when($from, fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('final_report_at', '>=', $from->toDateString())))
                        ->when($until, fn ($q) => $q->whereHas('matter', fn ($m) => $m->whereDate('final_report_at', '<=', $until->toDateString()))),
                )
                    ->columnSpan(3),
            ])->filtersLayout(FiltersLayout::AboveContent)
            ->queryStringIdentifier('final_report')
            ->persistSearchInSession()
            ->filtersFormWidth(Width::ExtraLarge)
            ->headerActions([
                ReportPrintAction::make(),
                ExportAction::make()
                    ->exporter(AssistantMatterFeesExporter::class)
                    ->fileDisk('public')
                    ->label(__('Export Excel'))
                    ->icon('heroicon-o-arrow-down-tray'),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return MatterParty::query()
            ->where('matter_party.role', 'expert')
            ->where('matter_party.type', 'assistant')
            ->with([
                'matter.court',
                'matter.type',
                'party',
            ])
            ->withSum(['matter_fees as total_matter_fees' => function ($q) {
                $q->where('type', '!=', FeeType::VAT->value);
            }], 'amount')
            ->withCount(['matter_assistants as assistants_count'])
            ->whereHas('matter');
    }
}
