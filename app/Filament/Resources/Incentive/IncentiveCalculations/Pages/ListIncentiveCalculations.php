<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Exports\AssistantMattersExporter;
use App\Filament\Exports\MattersCompletingDataExporter;
use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Models\Matter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListIncentiveCalculations extends ListRecords
{
    protected static string $resource = IncentiveCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [

            CreateAction::make(),
            ExportAction::make('matters')
                ->exporter(AssistantMattersExporter::class)
                ->label(__('Export'))
                ->schema([
                    Section::make('Initial Report Date')->schema([
                        DatePicker::make('initial_start_date')
                            ->label(__('Start Date'))
                            ->required(),
                        DatePicker::make('initial_end_date')
                            ->label(__('End Date'))
                            ->required(),
                    ])->columns(2),
                    Section::make('Final Report Date')->schema([
                        DatePicker::make('final_start_date')
                            ->label(__('Start Date'))
                            ->required(),
                        DatePicker::make('final_end_date')
                            ->label(__('End Date'))
                            ->required(),
                    ])->columns(2),
                ])->columnMappingColumns(2)
                ->modifyQueryUsing(function (Builder $query, array $options) {
                    // Filter by initial report date range
                    if (!empty($options['initial_start_date']) && !empty($options['initial_end_date'])) {
                        $query->whereHas('matter', function (Builder $q) use ($options) {
                            $q->whereBetween('initial_report_at', [
                                $options['initial_start_date'],
                                $options['initial_end_date'],
                            ]);
                        });
                    }

                    // Filter by final report date range (optional)
                    if (!empty($options['final_start_date']) && !empty($options['final_end_date'])) {
                        $query->whereHas('matter', function (Builder $q) use ($options) {
                            $q->whereBetween('final_report_at', [
                                $options['final_start_date'],
                                $options['final_end_date'],
                            ]);
                        });
                    }

                    return $query;
                }),
            ExportAction::make('data')
                ->modalWidth(Width::ThreeExtraLarge)
                ->schema([
                    DatePicker::make('start_date'),
                    DatePicker::make('end_date')
                ])
                ->columnMapping(false)
                ->model(Matter::class)
                ->icon(Heroicon::DocumentChartBar)
                ->exporter(MattersCompletingDataExporter::class)
                ->label(__('Check Data'))
                ->modifyQueryUsing(fn(Builder $query, array $options) => Matter::query()->whereBetween('final_report_at', [$options['start_date'], $options['end_date']])),
        ];
    }
}
