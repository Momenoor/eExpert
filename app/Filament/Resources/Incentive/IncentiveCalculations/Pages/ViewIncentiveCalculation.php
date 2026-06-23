<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Models\Party;
use App\Services\IncentiveCalculatorService;
use App\Services\IncentiveService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ViewIncentiveCalculation extends ViewRecord
{
    protected static string $resource = IncentiveCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn() => $this->record->isDraft()),
            Action::make('importMatters')
                ->label(__('Import Qualifying Matters'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn() => $this->record->isDraft())
                ->mountUsing(function ($form) {
                    $form->fill([
                        'expert_ids' => [],
                        'assistant_ids' => [],
                        'temp_lines' => [],
                    ]);
                })
                ->schema([
                    Select::make('expert_ids')
                        ->label(__('Experts'))
                        ->multiple()
                        ->options(Party::query()
                            ->whereJsonContains('role', ['role' => 'expert', 'type' => 'certified'])
                            ->pluck('name', 'id'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateQualifyingMatters($set, $get)),
                    Select::make('assistant_ids')
                        ->label(__('Assistants'))
                        ->multiple()
                        ->options(Party::query()
                            ->whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                            ->pluck('name', 'id'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Set $set, Get $get) => $this->updateQualifyingMatters($set, $get)),
                    Repeater::make('temp_lines')
                        ->label('')
                        ->schema([
                            Checkbox::make('is_selected')
                                ->hiddenLabel()
                                ->label('Is Selected'),
                            TextInput::make('reference')
                                ->label(__('Matter'))
                                ->disabled(),
                            TextInput::make('court_name')->label(__('Court'))->disabled(),
                            TextInput::make('assistant_names')->label(__('Assistants'))->disabled(),
                            TextInput::make('fees_amount')->label(__('Fees'))->numeric()->disabled(),
                            TextInput::make('collected_fees_amount')->label(__('Collected'))->numeric()->disabled(),
                            TextInput::make('net_collected_amount')->label(__('Net Basis'))->numeric()->disabled(),
                        ])
                        ->columns(7)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ])
                ->action(function (array $data) {
                    $selectedMatterIds = collect($data['temp_lines'])
                        ->where('is_selected', true)
                        ->pluck('matter_id')
                        ->toArray();

                    app(IncentiveService::class)->importSelectedMatters($this->record, $selectedMatterIds);
                    Notification::make()
                        ->title(__('Matters Imported'))
                        ->success()
                        ->send();
                    $this->refreshFormData([]);
                })
                ->modalWidth('7xl'),
            Action::make('calculate')
                ->label(__('Run Calculation'))
                ->icon('heroicon-o-play')
                ->color('info')
                ->visible(fn() => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalHeading(__('Run Incentive Calculation'))
                ->modalDescription(__('This will clear and recalculate all lines for this period. Matters with initial_report_at within the period and paid fees not yet in a finalized calculation will be included.'))
                ->modalIcon('heroicon-o-calculator')
                ->action(function () {
                    try {
                        app(IncentiveCalculatorService::class)->calculate($this->record);
                        $this->refreshFormData([]);

                        $lineCount = $this->record->lines()->count();
                        Notification::make()
                            ->title(__('Calculation Complete'))
                            ->body(__('Calculated') . ' ' . $lineCount . ' ' . __('fee lines.'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Calculation Failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('manageDeductions')
                ->label(__('Manage Deductions'))
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->visible(fn() => $this->record->isDraft() && $this->record->lines()->exists())
                ->url(fn() => IncentiveCalculationResource::getUrl('deductions', ['record' => $this->record])),

            Action::make('finalize')
                ->label(__('Finalize'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn() => $this->record->isDraft() && $this->record->lines()->exists())
                ->requiresConfirmation()
                ->modalHeading(__('Finalize Calculation'))
                ->modalDescription(__('Once finalized, this calculation cannot be edited or recalculated. All included fees will be locked and excluded from future calculations. This action cannot be undone.'))
                ->modalIcon('heroicon-o-lock-closed')
                ->modalIconColor('success')
                ->action(function () {
                    try {
                        app(IncentiveCalculatorService::class)->finalize($this->record);
                        $this->refreshFormData([]);

                        Notification::make()
                            ->title(__('Calculation Finalized'))
                            ->body(__('This calculation has been locked and finalized.'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Finalization Failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('printSummary')
                ->label(__('Print Summary'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn() => route('incentive.calculation.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function updateQualifyingMatters(Set $set, Get $get): void
    {
        $service = app(IncentiveService::class);
        $matters = $service->getQualifyingMatters(
            $this->record->period_start,
            $this->record->period_end,
            [
                'expert_ids' => $get('expert_ids'),
                'assistant_ids' => $get('assistant_ids'),
            ]
        );
        $data = $service->calculateMattersData($matters, $this->record->period_start, $this->record->period_end);

        $set('temp_lines', $data->map(fn($item) => array_merge($item, ['is_selected' => true]))->toArray());
    }
}
