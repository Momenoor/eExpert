<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Models\IncentiveAssistantLine;
use App\Services\IncentiveCalculatorService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ManageIncentiveDeductions extends ViewRecord
{
    protected static string $resource = IncentiveCalculationResource::class;

    protected string $view = 'filament.pages.incentive.manage-incentive-deductions';

    public function getTitle(): string
    {
        return __('Manage Deductions').' — '.$this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Back to Calculation'))
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => IncentiveCalculationResource::getUrl('view', ['record' => $this->record]))
                ->color('gray'),

            Action::make('recalculate')
                ->label(__('Recalculate'))
                ->icon('heroicon-o-arrow-path')
                ->color('info')

                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->action(function () {
                    app(IncentiveCalculatorService::class)->calculate($this->record);
                    $this->refreshFormData([]);
                    Notification::make()->title(__('Recalculated'))->success()->send();
                }),
        ];
    }

    public function getLinesWithDeductions(): Collection
    {
        return $this->record->lines()
            ->with(['matter', 'deductions', 'assistantLines.party'])
            ->get()
            ->map(fn ($line) => [
                'line' => $line,
                'matter' => $line->matter,
                'deductions' => $line->deductions,
                'assistants' => $line->assistantLines->map(fn ($al) => [
                    'name' => $al->party->name,
                    'share' => $al->share_amount,
                    'extra' => $al->extra_amount,
                    'penalty' => $al->minimum_penalty_amount,
                    'total' => $al->total_amount,
                ]),
            ]);
    }

    public function getAssistantSummary(): Collection
    {
        return $this->record->assistantExtras()->with('party')->get()
            ->map(function ($extra) {
                $lines = IncentiveAssistantLine::whereHas(
                    'incentiveLine',
                    fn ($q) => $q->where('incentive_calculation_id', $this->record->id)
                )->where('party_id', $extra->party_id)->get();

                return [
                    'extra' => $extra,
                    'party' => $extra->party,
                    'share_total' => $lines->sum('share_amount'),
                    'fixed_deduction' => $extra->fixed_deduction,
                    'total' => max(0, $lines->sum('total_amount') - $extra->fixed_deduction),
                ];
            });
    }

    public function getLinesTotalBase(): float
    {
        return (float) $this->record->lines->sum('base_amount');
    }

    public function getLinesTotalNet(): float
    {
        return (float) $this->record->lines->sum('net_amount');
    }

    public function getGrandTotalShare(): float
    {
        return (float) IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $this->record->id)
        )->sum('share_amount');
    }

    public function getGrandTotal(): float
    {
        $assistantLinesSum = (float) IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $this->record->id)
        )->sum('total_amount');

        $fixedDeductionsSum = (float) $this->record->assistantExtras->sum('fixed_deduction');

        return max(0, $assistantLinesSum - $fixedDeductionsSum);
    }
}
