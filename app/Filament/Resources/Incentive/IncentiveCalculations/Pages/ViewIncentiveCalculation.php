<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Pages;

use App\Filament\Resources\Incentive\IncentiveCalculations\IncentiveCalculationResource;
use App\Filament\Widgets\IncentiveSummaryTableWidget;
use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveLine;
use App\Models\Matter;
use App\Models\Party;
use App\Services\IncentiveCalculatorService;
use App\Services\IncentiveService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
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

    protected function getFooterWidgets(): array
    {
        return [
            IncentiveSummaryTableWidget::make(['calculationId' => $this->record->id]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn () => $this->record->isDraft()),
            Action::make('importMatters')
                ->label(__('Import Qualifying Matters'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->authorize('runCalculation', $this->record)
                ->visible(fn () => $this->record->isDraft())
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
                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->updateQualifyingMatters($set, $get)),
                    Select::make('assistant_ids')
                        ->label(__('Assistants'))
                        ->multiple()
                        ->options(Party::query()
                            ->whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                            ->pluck('name', 'id'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->updateQualifyingMatters($set, $get)),
                    Repeater::make('temp_lines')
                        ->label('')
                        ->schema([
                            // Without this the action's ->pluck('matter_id') returned
                            // an array of nulls and the import silently did nothing:
                            // a repeater only dehydrates the fields it declares, and
                            // every other field here is ->disabled() (also not
                            // dehydrated), so only is_selected survived.
                            Hidden::make('matter_id'),
                            Checkbox::make('is_selected')
                                ->hiddenLabel(),
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
                    $selectedMatterIds = collect($data['temp_lines'] ?? [])
                        ->where('is_selected', true)
                        ->pluck('matter_id')
                        ->filter()
                        ->values()
                        ->all();

                    if ($selectedMatterIds === []) {
                        Notification::make()
                            ->title(__('No matters selected'))
                            ->warning()
                            ->send();

                        return;
                    }

                    app(IncentiveService::class)->importSelectedMatters($this->record, $selectedMatterIds);
                    Notification::make()
                        ->title(__('Matters Imported'))
                        ->success()
                        ->send();
                    $this->refreshFormData([]);
                    $this->dispatch('incentiveCalculationUpdated');
                })
                ->modalWidth('7xl'),

            Action::make('importAllQualifyingMatters')
                ->label(__('Import All Qualifying Matters'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->authorize('runCalculation', $this->record)
                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalHeading(__('Import All Qualifying Matters'))
                ->modalDescription(__('Imports every matter that qualifies for this period across all experts and assistants, without needing to pick a filter first.'))
                ->action(function () {
                    $service = app(IncentiveService::class);

                    $allExpertIds = Party::query()
                        ->whereJsonContains('role', ['role' => 'expert', 'type' => 'certified'])
                        ->pluck('id')
                        ->toArray();
                    $allAssistantIds = Party::query()
                        ->whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                        ->pluck('id')
                        ->toArray();

                    $matters = $service->getQualifyingMatters(
                        $this->record->period_start,
                        $this->record->period_end,
                        ['expert_ids' => $allExpertIds, 'assistant_ids' => $allAssistantIds]
                    );

                    $service->importSelectedMatters($this->record, $matters->pluck('id')->toArray());

                    $this->refreshFormData([]);
                    $this->dispatch('incentiveCalculationUpdated');
                    Notification::make()
                        ->title(__('Matters Imported'))
                        ->body(__('Imported').' '.$matters->count().' '.__('matters.'))
                        ->success()
                        ->send();
                }),

            Action::make('addSpecificMatter')
                ->label(__('Add Specific Matter'))
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->authorize('runCalculation', $this->record)
                ->visible(fn () => $this->record->isDraft())
                ->modalHeading(__('Add Specific Matter'))
                ->modalDescription(__('Imports this matter regardless of its period or trigger date. Any fee already included in another calculation is skipped automatically.'))
                ->schema([
                    Select::make('matter_id')
                        ->label(__('Matter'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Matter::query()
                            ->with(['type', 'court'])
                            ->where(function ($q) use ($search) {
                                // Matter reference is "number/year" (e.g. 70/2026), but
                                // match the reversed "year/number" order too since some
                                // people naturally type it that way.
                                $q->whereRaw("CONCAT(number, '/', year) LIKE ?", ["%{$search}%"])
                                    ->orWhereRaw("CONCAT(year, '/', number) LIKE ?", ["%{$search}%"])
                                    ->orWhere('number', 'like', "%{$search}%")
                                    ->orWhere('year', 'like', "%{$search}%")
                                    ->orWhereHas('type', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                                    ->orWhereHas('court', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->id => $this->matterOptionLabel($m)]))
                        ->getOptionLabelUsing(fn ($value) => $this->matterOptionLabel(Matter::with(['type', 'court'])->find($value)))
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $imported = app(IncentiveService::class)->forceImportMatter($this->record, (int) $data['matter_id']);
                        $this->refreshFormData([]);
                        $this->dispatch('incentiveCalculationUpdated');

                        if ($imported > 0) {
                            Notification::make()
                                ->title(__('Matter Added'))
                                ->body(trans_choice(':count fee line imported.|:count fee lines imported.', $imported, ['count' => $imported]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('Nothing to Import'))
                                ->body(__('Every eligible fee for this matter is already included in a calculation.'))
                                ->warning()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Import Failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('calculate')
                ->label(__('Run Calculation'))
                ->icon('heroicon-o-play')
                ->color('info')
                ->authorize('runCalculation', $this->record)
                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalHeading(__('Run Incentive Calculation'))
                ->modalDescription(__('This will clear and recalculate all lines for this period. Matters with initial_report_at within the period and paid fees not yet in a finalized calculation will be included.'))
                ->modalIcon('heroicon-o-calculator')
                ->action(function () {
                    try {
                        app(IncentiveCalculatorService::class)->calculate($this->record);
                        $this->refreshFormData([]);
                        $this->dispatch('incentiveCalculationUpdated');

                        $lineCount = $this->record->lines()->count();
                        Notification::make()
                            ->title(__('Calculation Complete'))
                            ->body(__('Calculated').' '.$lineCount.' '.__('fee lines.'))
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

            Action::make('deleteAllLines')
                ->label(__('Delete All Lines'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize('runCalculation', $this->record)
                ->visible(fn () => $this->record->isDraft() && $this->record->lines()->exists())
                ->requiresConfirmation()
                ->modalHeading(__('Delete All Lines'))
                ->modalDescription(__('This permanently removes every imported matter, deduction, and assistant share from this calculation, resetting it back to empty. This action cannot be undone.'))
                ->modalIcon('heroicon-o-trash')
                ->modalIconColor('danger')
                ->action(function () {
                    IncentiveLine::where('incentive_calculation_id', $this->record->id)->delete();
                    IncentiveAssistantExtra::where('incentive_calculation_id', $this->record->id)->delete();

                    $this->refreshFormData([]);
                    $this->dispatch('incentiveCalculationUpdated');
                    Notification::make()
                        ->title(__('All lines deleted'))
                        ->success()
                        ->send();
                }),

            Action::make('finalize')
                ->label(__('Finalize'))
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->authorize('finalize', $this->record)
                ->visible(fn () => $this->record->isDraft() && $this->record->lines()->exists())
                ->requiresConfirmation()
                ->modalHeading(__('Finalize Calculation'))
                ->modalDescription(__('Once finalized, this calculation cannot be edited or recalculated. All included fees will be locked and excluded from future calculations. This action cannot be undone.'))
                ->modalIcon('heroicon-o-lock-closed')
                ->modalIconColor('success')
                ->action(function () {
                    try {
                        app(IncentiveCalculatorService::class)->finalize($this->record);
                        $this->refreshFormData([]);
                        $this->dispatch('incentiveCalculationUpdated');

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
                ->url(fn () => route('incentive.calculation.print', $this->record))
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

        $set('temp_lines', $data->map(fn ($item) => array_merge($item, ['is_selected' => true]))->toArray());
    }

    /**
     * Matter option label for the "Add Specific Matter" select: reference,
     * type, and court name together so matters can be told apart when
     * searching (e.g. re-finding one just removed from this calculation).
     */
    protected function matterOptionLabel(?Matter $matter): ?string
    {
        if (! $matter) {
            return null;
        }

        return collect([$matter->reference, $matter->type?->name, $matter->court?->name])
            ->filter()
            ->implode(' — ');
    }
}
