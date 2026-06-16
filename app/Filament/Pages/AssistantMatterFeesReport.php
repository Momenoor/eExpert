<?php

namespace App\Filament\Pages;

use App\Enums\FeeType;
use App\Models\Matter;
use App\Models\MatterParty;
use App\Models\Party;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Exports\AssistantMatterFeesExporter;

class AssistantMatterFeesReport extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|null|\UnitEnum $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.assistant-matter-fees-report';
    protected static ?string $navigationLabel = 'Assistant Fees Report';

    public static function getNavigationGroup(): string|null|\UnitEnum
    {
        return __(parent::getNavigationGroup());
    }

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
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('matter.reference')
                    ->label(__('Matter'))
                    ->getStateUsing(fn($record) => $record->matter->year . '/' . $record->matter->number)
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
                    ->getStateUsing(fn($record) => ($record->assistants_count > 0) ? ($record->total_matter_fees / $record->assistants_count) : 0)
                    ->money('AED')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('party_id')
                    ->label(__('Assistant'))
                    ->options(Party::whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                        ->orderBy('name')
                        ->pluck('name', 'id')
                    )
                    ->searchable(),
                Filter::make('final_report_at')
                    ->label(__('Final Report Date'))
                    ->schema([
                        Section::make(__('Final Report Date'))->schema([
                            DatePicker::make('final_from')->label(__('From')),
                            DatePicker::make('final_until')->label(__('Until')),
                        ])->columns(2),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $query
                            ->when($data['final_from'], fn($q) => $q->whereHas('matter', fn($m) => $m->whereDate('final_report_at', '>=', $data['final_from'])))
                            ->when($data['final_until'], fn($q) => $q->whereHas('matter', fn($m) => $m->whereDate('final_report_at', '<=', $data['final_until'])));
                    })->indicateUsing(fn($data) => ($data['final_from'] || $data['final_until'] ? __('Final Report Date') . ' ' . ($data['final_from'] ? __('From:') . $data['final_from'] : '') . ($data['final_until'] ? ' '.__('Until:') . $data['final_until'] : ''):'')),
            ])
            ->persistSearchInSession()
            ->filtersFormWidth(Width::ExtraLarge)
            ->headerActions([
                ExportAction::make()
                    ->exporter(AssistantMatterFeesExporter::class)
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
