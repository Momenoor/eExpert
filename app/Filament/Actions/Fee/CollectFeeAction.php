<?php

namespace App\Filament\Actions\Fee;

use App\Models\Allocation;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Size;

class CollectFeeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'collect_fee';
    }

    private static function actionLabel($record): string
    {
        return $record->type?->isNegative() ? __('Pay Fee') : __('Collect Fee');
    }

    private static function overpaidLabel($record): string
    {
        return __('Overpaid');
    }

    private static function fullyPaidLabel($record): string
    {
        return __('Fully Paid');
    }

    private static function modalHeadingLabel($record): string
    {
        return $record->type?->isNegative()
            ? __('Pay Fee').': '.number_format(abs($record?->amount), 2)
            : __('Collect Payment — Fee').': '.number_format(abs($record?->amount), 2);
    }

    private static function amountLabel($record): string
    {
        return $record->type?->isNegative()
            ? __('Amount to Pay')
            : __('Amount to Collect');
    }

    public function setUp(): void
    {
        parent::setUp();
        $this
            ->label(function ($record) {
                $absCollected = abs(static::collectedAmount($record));
                $absAmount = abs((float) ($record?->amount ?? 0));

                return match (true) {
                    $absCollected > $absAmount => __('Overpaid'),
                    $absCollected === $absAmount => __('Fully Paid'),
                    default => static::actionLabel($record), // ✅ Pay Fee / Collect Fee
                };
            })
            ->size(Size::Large)
            ->icon(fn ($record) => abs(static::collectedAmount($record)) >= abs((float) ($record?->amount ?? 0))
                ? 'heroicon-o-check-badge'
                : 'heroicon-o-plus-circle'
            )
            ->color(fn ($record) => abs(static::collectedAmount($record)) > abs((float) ($record?->amount ?? 0))
                ? 'warning' : ($record->type?->isNegative() ? 'danger' : 'success')
            )
            ->visible(fn ($record) => auth()->user()->can('CollectFee:Matter'))
            ->disabled(fn ($record) => abs(static::collectedAmount($record)) >= abs((float) ($record?->amount ?? 0)))
            ->tooltip(fn ($record) => match (true) {
                abs(static::collectedAmount($record)) > abs((float) ($record?->amount ?? 0)) => __('Overpaid by').' '.number_format(abs(static::collectedAmount($record)) - abs((float) $record->amount), 2),
                abs(static::collectedAmount($record)) === abs((float) ($record?->amount ?? 0)) => __('This fee is fully paid'),
                default => __('Remaining balance').': '.number_format(abs(static::feeBalance($record)), 2),
            })
            ->modalHeading(fn ($record) => static::modalHeadingLabel($record))   // ✅ Pay Fee / Collect Payment
            ->modalDescription(fn ($record) => ($record->type?->isNegative() ? __('Paid so far') : __('Collected so far')).': '.number_format(abs(static::collectedAmount($record)), 2)
                .' · '.__('Remaining balance').': '.number_format(abs(static::feeBalance($record)), 2)
            )
            ->modalWidth('md')
            ->schema(fn ($record) => [
                TextInput::make('amount')
                    ->label(static::amountLabel($record))                        // ✅ Amount to Pay / Amount to Collect
                    ->numeric()
                    ->prefix('AED')
                    ->minValue(0.01)
                    ->maxValue(abs(static::feeBalance($record)))
                    ->default(abs(static::feeBalance($record)))
                    ->required()
                    ->helperText(__('Max allowed').': '.number_format(abs(static::feeBalance($record)), 2)),
                DatePicker::make('date')->label(__('Payment Date'))->default(now())->required(),
                Textarea::make('description')
                    ->label(__('Notes / Reference'))
                    ->rows(2)
                    ->placeholder(__('Cheque number, bank transfer ref, etc.')),
            ])
            ->action(function (array $data, $record, $component) {
                $amount = $record->type?->isNegative()
                    ? -abs($data['amount'])
                    : abs($data['amount']);

                Allocation::create([
                    'matter_id' => $record->matter_id,
                    'fee_id' => $record->id,
                    'amount' => $amount,
                    'date' => $data['date'],
                    'description' => $data['description'] ?? null,
                ]);

                $record->matter->updateCollectionStatus();
                $record->refresh();
                $record->unsetRelation('allocations');
            })
            ->successNotificationTitle(fn ($record) => $record->type?->isNegative()  // ✅
                ? __('Payment recorded successfully.')
                : __('Collection recorded successfully.')
            );
    }

    private static function collectedAmount($record): float
    {
        return (float) ($record?->allocations?->sum('amount') ?? 0);
    }

    private static function feeBalance($record): float
    {
        return (float) ($record?->amount ?? 0) - static::collectedAmount($record);
    }
}
