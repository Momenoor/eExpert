<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Services\IncentiveCalculatorService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewIncentiveCalculation extends ViewRecord
{
    protected static string $resource = IncentiveCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn() => $this->record->isDraft()),
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
}
