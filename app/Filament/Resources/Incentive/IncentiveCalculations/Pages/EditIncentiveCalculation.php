<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Models\Party;
use App\Services\IncentiveService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class EditIncentiveCalculation extends EditRecord
{
    protected static string $resource = IncentiveCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('importMatters')
                ->label(__('Import Qualifying Matters'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn () => $this->record->isDraft())
                ->mountUsing(function ($form) {
                    $form->fill([
                        'expert_ids' => [],
                        'assistant_ids' => [],
                        'temp_lines' => [],
                    ]);
                })
                ->form([
                    Select::make('expert_ids')
                        ->label(__('Experts'))
                        ->multiple()
                        ->options(Party::query()
                            ->whereJsonContains('role', ['role' => 'expert', 'type' => 'certified'])
                            ->pluck('name', 'id'))
                        ->live()
                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->updateQualifyingMatters($set, $get)),
                    Select::make('assistant_ids')
                        ->label(__('Assistants'))
                        ->multiple()
                        ->options(Party::query()
                            ->whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                            ->pluck('name', 'id'))
                        ->live()
                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->updateQualifyingMatters($set, $get)),
                    Repeater::make('temp_lines')
                        ->label('')
                        ->schema([
                            Checkbox::make('is_selected')
                                ->hiddenLabel(),
                            TextInput::make('reference')
                                ->label(__('Matter'))
                                ->disabled(),
                            TextInput::make('court_name')->label(__('Court'))->disabled(),
                            TextInput::make('assistant_names')->label(__('Assistants'))->disabled(),
                            TextInput::make('collected_fees_amount')->label(__('Collected'))->numeric()->disabled(),
                            TextInput::make('net_collected_amount')->label(__('Net Basis'))->numeric()->disabled(),
                        ])
                        ->columns(6)
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
            DeleteAction::make(),
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

        $set('temp_lines', $data->map(fn ($item) => array_merge($item, ['is_selected' => true]))->toArray());
    }
}
