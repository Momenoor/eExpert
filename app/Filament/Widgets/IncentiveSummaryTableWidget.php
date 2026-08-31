<?php

namespace App\Filament\Widgets;

use App\Models\IncentiveAssistantExtra;
use App\Models\IncentiveAssistantLine;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveLine;
use App\Services\IncentiveCalculatorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class IncentiveSummaryTableWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public ?int $calculationId = null;

    /**
     * Widgets are independent Livewire components — they don't automatically
     * re-render just because a header action on the parent page mutated the
     * data. The page dispatches this event after every action that changes
     * lines/deductions/extras so the table refreshes without a full reload.
     */
    #[On('incentiveCalculationUpdated')]
    public function refreshFromCalculation(): void
    {
        //
    }

    protected function isCalculationDraft(): bool
    {
        return IncentiveCalculation::find($this->calculationId)?->isDraft() ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return IncentiveAssistantLine::query()
            ->whereHas('incentiveLine', fn ($q) => $q->where('incentive_calculation_id', $this->calculationId))
            ->with(['party', 'incentiveLine.matter.court', 'incentiveLine.matter.type', 'incentiveLine.deductions']);
    }
    private static function splitSearch(string $search): array
    {
        return $search
                |> trim(...)
                |> (fn($x) => preg_split('/[\s\/\\\\\-]+/', $x))
                |> (fn($x) => array_filter($x, fn($token) => strlen($token) > 0))
                |> array_values(...);
    }

    private static function applyMultiWordSearch(Builder $query, string $search, array $columns): Builder
    {
        $tokens = static::splitSearch($search);
        foreach ($tokens as $token) {
            $query->where(function (Builder $query) use ($token, $columns) {
                foreach ($columns as $i => $column) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    if (str_contains($column, '.')) {
                        [$relation, $col] = explode('.', $column, 2);
                        $query->{$i === 0 ? 'whereHas' : 'orWhereHas'}($relation, fn($r) => $r->where($col, 'like', "%{$token}%"));
                    } else {
                        $query->{$method}($column, 'like', "%{$token}%");
                    }
                }
            });
        }
        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Incentive Summary'))
            ->paginationPageOptions([25, 50, 100, 200])
            ->defaultPaginationPageOption(100)
            ->groups([
                Group::make('party_id')
                    ->label(__('Assistant'))
                    ->getTitleFromRecordUsing(fn ($record) => $record->party?->name ?? '—')
                    ->getDescriptionFromRecordUsing(fn ($record) => $this->describeAssistant($record->party_id)),
            ])
            ->defaultGroup('party_id')
            ->columns([
                TextColumn::make('incentiveLine.matter.reference')
                    ->label(__('Matter'))
                    ->url(fn ($record) => $record->incentiveLine?->matter_id
                        ? route('filament.admin.resources.matters.view', $record->incentiveLine->matter_id)
                        : null)
                    ->openUrlInNewTab()
                    ->searchable(query: function (Builder $query, string $search) {
                        $tokens = static::splitSearch($search);
                        if (count($tokens) === 2 && is_numeric($tokens[0]) && is_numeric($tokens[1])) {
                            return $query->where(function ($q) use ($tokens) {
                                foreach ($tokens as $token) {
                                    $q->where(function ($inner) use ($token) {
                                        $inner->orWhere('year', $token)
                                            ->orWhere('number', $token)
                                            ->orWhere('number', "0" . $token);
                                    });
                                }
                            });
                        }
                        return static::applyMultiWordSearch($query, $search, ['year', 'number']);
                    })
                    ->weight('bold'),
                TextColumn::make('difficulty')
                    ->label(__('Difficulty'))
                    ->getStateUsing(fn ($record) => collect([
                        $record->incentiveLine?->matter?->difficulty,
                        $record->incentiveLine?->matter?->commissioning,
                    ])->filter()->values()->all())
                    ->badge()
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('case_info')
                    ->label(__('Court / Type'))
                    ->getStateUsing(fn ($record) => collect([
                        $record->incentiveLine?->matter?->type?->name,
                        $record->incentiveLine?->matter?->court?->name,
                    ])->filter()->values()->all())
                    ->badge()
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('month_label')
                    ->label(__('Month'))
                    ->getStateUsing(fn ($record) => $record->incentiveLine?->matter
                        ? app(IncentiveCalculatorService::class)->matterMonthLabel($record->incentiveLine->matter, $this->calculationId)
                        : null)
                    ->placeholder('—'),
                TextColumn::make('incentiveLine.completion_days')
                    ->label(__('Days'))
                    ->placeholder('—'),
                TextColumn::make('incentiveLine.fee_amount_excl_vat')
                    ->label(__('Fee'))
                    ->money('AED'),
                TextColumn::make('incentiveLine.effective_percentage')
                    ->label(__('Rate %'))
                    ->suffix('%')
                    ->description(fn ($record) => $this->describeAssistantRate($record)),
                TextColumn::make('incentiveLine.base_amount')
                    ->label(__('Base Amount'))
                    ->money('AED'),
                TextColumn::make('incentiveLine.total_deduction_pct')
                    ->label(__('Deductions'))
                    ->suffix('%')
                    ->width('1%')
                    ->color('danger')
                    ->placeholder('0%')
                    ->description(fn ($record) => $this->describeDeductions($record)),
                TextColumn::make('share_amount')
                    ->label(__('Share'))
                    ->money('AED'),
                TextColumn::make('extra_amount')
                    ->label(__('Extra'))
                    ->money('AED')
                    ->placeholder('—')
                    ->color('success')
                    ->description(fn ($record) => app(IncentiveCalculatorService::class)
                        ->describeExtraReason((float) $record->extra_percentage)),
                TextColumn::make('minimum_penalty_amount')
                    ->label(__('Penalty'))
                    ->money('AED')
                    ->placeholder('—')
                    ->color('danger')
                    ->description(fn ($record) => app(IncentiveCalculatorService::class)
                        ->describePenaltyReason((float) $record->minimum_penalty_pct)),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->money('AED')
                    ->weight('bold'),
            ])
            ->recordActions([
                Action::make('editPercentage')
                    ->label(__('Edit %'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip(fn ($record) => __('Override Percentage for :name', ['name' => $record->party?->name ?? '—']))
                    ->modalHeading(fn ($record) => __('Override Percentage for :name', ['name' => $record->party?->name ?? '—']))
                    ->modalDescription(__('This becomes this specific assistant\'s final effective percentage of the fee for this matter — it does not affect any other assistant sharing the same matter. Deductions still apply on top. Leave empty to use the automatic calculation. The calculation is reapplied immediately.'))
                    ->disabled(fn () => ! $this->isCalculationDraft())
                    ->schema([
                        TextInput::make('percentage_override')
                            ->label(__('Override %'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->fillForm(fn ($record) => [
                        'percentage_override' => $record->percentage_override,
                    ])
                    ->action(function (array $data, $record) {
                        $record->update([
                            'percentage_override' => filled($data['percentage_override']) ? $data['percentage_override'] : null,
                        ]);

                        app(IncentiveCalculatorService::class)->calculate(
                            IncentiveCalculation::findOrFail($this->calculationId)
                        );

                        Notification::make()->title(__('Saved'))->success()->send();
                    }),
                Action::make('addDeduction')
                    ->label(fn ($record) => __('Deduct from :name', ['name' => $record->party?->name ?? '—']))
                    ->icon('heroicon-o-minus-circle')
                    ->iconButton()
                    ->tooltip(fn ($record) => __('Deduct from :name', ['name' => $record->party?->name ?? '—']))
                    ->modalHeading(__('Deductible Amount'))
                    ->modalDescription(__('An amount subtracted from this assistant\'s total incentive for this calculation (e.g. a penalty or reimbursement).'))
                    ->disabled(fn () => ! $this->isCalculationDraft())
                    ->schema([
                        TextInput::make('fixed_deduction')
                            ->label(__('Deduction Amount'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Textarea::make('fixed_deduction_reason')
                            ->label(__('Reason'))
                            ->rows(2),
                    ])
                    ->fillForm(function ($record) {
                        $extra = IncentiveAssistantExtra::where('incentive_calculation_id', $this->calculationId)
                            ->where('party_id', $record->party_id)
                            ->first();

                        return [
                            'fixed_deduction' => $extra?->fixed_deduction ?? 0,
                            'fixed_deduction_reason' => $extra?->fixed_deduction_reason,
                        ];
                    })
                    ->action(function (array $data, $record) {
                        IncentiveAssistantExtra::where('incentive_calculation_id', $this->calculationId)
                            ->where('party_id', $record->party_id)
                            ->update([
                                'fixed_deduction' => $data['fixed_deduction'] ?? 0,
                                'fixed_deduction_reason' => $data['fixed_deduction_reason'] ?? null,
                            ]);

                        app(IncentiveCalculatorService::class)->calculate(
                            IncentiveCalculation::findOrFail($this->calculationId)
                        );

                        Notification::make()->title(__('Deduction saved'))->success()->send();
                    }),
                Action::make('printAssistantReport')
                    ->label(fn ($record) => __('Print Report for :name', ['name' => $record->party?->name ?? '—']))
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->tooltip(fn ($record) => __('Print Report for :name', ['name' => $record->party?->name ?? '—']))
                    ->color('gray')
                    ->url(fn ($record) => route('incentive.calculation.print.assistant', [
                        'calculation' => $this->calculationId,
                        'party' => $record->party_id,
                    ]))
                    ->openUrlInNewTab(),
                Action::make('deleteMatter')
                    ->label(fn ($record) => __('Remove :matter from Calculation', ['matter' => $record->incentiveLine?->matter?->reference ?? '—']))
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->color('danger')
                    ->tooltip(fn ($record) => __('Remove :matter from Calculation', ['matter' => $record->incentiveLine?->matter?->reference ?? '—']))
                    ->disabled(fn () => ! $this->isCalculationDraft())
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => __('Remove :matter from Calculation', ['matter' => $record->incentiveLine?->matter?->reference ?? '—']))
                    ->modalDescription(__('This removes this matter\'s lines, deductions, and assistant shares from this calculation only — other matters are unaffected. This action cannot be undone.'))
                    ->modalIcon('heroicon-o-trash')
                    ->modalIconColor('danger')
                    ->action(function ($record) {
                        $matterId = $record->incentiveLine?->matter_id;

                        IncentiveLine::where('incentive_calculation_id', $this->calculationId)
                            ->where('matter_id', $matterId)
                            ->delete();

                        app(IncentiveCalculatorService::class)->calculate(
                            IncentiveCalculation::findOrFail($this->calculationId)
                        );

                        Notification::make()->title(__('Matter Removed'))->success()->send();
                    }),
            ]);
    }

    /**
     * "Rate %" shows the matter's own overall effective_percentage — the
     * same value on every assistant row sharing that matter, since it's a
     * property of the case, not of the assistant. When co-assistants split
     * the case unevenly (commission_percentage weights), that shared rate
     * can look wrong next to this specific assistant's actual share, so
     * surface their real cut (share_amount as a % of the fee) alongside it.
     */
    private function describeAssistantRate($record): ?string
    {
        $parts = [];

        if ($record->percentage_override !== null) {
            $parts[] = __('override');
        }

        $feeAmount = (float) ($record->incentiveLine?->fee_amount_excl_vat ?? 0);
        if ($feeAmount > 0) {
            $ownPct = round((float) $record->share_amount / $feeAmount * 100, 2);
            if ($ownPct != $record->incentiveLine?->effective_percentage) {
                $parts[] = __('Your share').': '.$ownPct.'%';
            }
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /**
     * Reason text for this matter's deductions — the type plus the
     * auto-generated note explaining why it was applied (e.g. "Substantive
     * changes in first review (−2%)").
     */
    private function describeDeductions($record): ?HtmlString
    {
        $deductions = $record->incentiveLine?->deductions;

        if (blank($deductions) || $deductions->isEmpty()) {
            return null;
        }

        $lines = $deductions->map(function ($d) {
            $label = '−'.$d->percentage.'% ('.__($d->type).')';

            return e($d->notes ?? $label);
        })->implode('<br>');

        return new HtmlString($lines);
    }

    private function describeAssistant(int $partyId): string
    {
        $extra = IncentiveAssistantExtra::where('incentive_calculation_id', $this->calculationId)
            ->where('party_id', $partyId)
            ->first();

        if (! $extra) {
            return '';
        }

        $parts = [
            __('Completed').': '.$extra->completed_matter_count,
            $extra->meets_minimum ? __('Meets Minimum') : __('Below Minimum'),
            __('Share Total').': '.number_format(
                IncentiveAssistantLine::whereHas(
                    'incentiveLine',
                    fn ($q) => $q->where('incentive_calculation_id', $this->calculationId)
                )->where('party_id', $partyId)->sum('share_amount'),
                2
            ),
        ];

        if ($extra->extra_percentage > 0) {
            $parts[] = __('Extra %').': +'.$extra->extra_percentage.'%';
        }

        if ($extra->minimum_penalty_pct > 0) {
            $parts[] = __('Penalty %').': -'.$extra->minimum_penalty_pct.'%';
        }

        if ($extra->fixed_deduction > 0) {
            $parts[] = __('Fixed Ded.').': '.number_format($extra->fixed_deduction, 2)
                .($extra->fixed_deduction_reason ? ' ('.$extra->fixed_deduction_reason.')' : '');
        }

        $total = max(0.0, IncentiveAssistantLine::whereHas(
            'incentiveLine',
            fn ($q) => $q->where('incentive_calculation_id', $this->calculationId)
        )->where('party_id', $partyId)->sum('total_amount') - $extra->fixed_deduction);

        $parts[] = __('Total').': '.number_format($total, 2);

        return implode(' · ', $parts);
    }
}
