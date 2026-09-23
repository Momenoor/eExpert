<?php

namespace App\Filament\Pms\Resources\Leases\RelationManagers;

use App\Enums\PMS\InstallmentPaymentMethod;
use App\Enums\PMS\InstallmentPaymentStatus;
use App\Models\Installment;
use App\Models\Lease;
use App\Services\MMS\PaymentService;
use App\Services\PMS\InstallmentGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * The instalment schedule for a lease. Generation is one action, not a
 * form field on the lease itself — a lease can only ever be split
 * once (`InstallmentGenerator` refuses a second schedule), so there is
 * nothing to edit here afterward except recording payments against rows
 * that already exist.
 */
class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Instalments');
    }

    private function lease(): Lease
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof Lease) {
            throw new LogicException('This relation manager only attaches to a lease.');
        }

        return $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('due_date')
            ->columns([
                TextColumn::make('due_date')
                    ->label(__('Due Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('net_amount')
                    ->label(__('Net'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('vat_amount')
                    ->label(__('VAT'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('total_due_amount')
                    ->label(__('Total Due'))
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Sum::make()->label(__('Total'))),
                TextColumn::make('paid_amount')
                    ->label(__('Paid'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('balance_due')
                    ->label(__('Balance'))
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold'),
                TextColumn::make('payment_method')
                    ->label(__('Method'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('bank_name')
                    ->label(__('Bank'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->defaultSort('due_date')
            ->filters([
                SelectFilter::make('payment_status')
                    ->label(__('Status'))
                    ->options(InstallmentPaymentStatus::class),
            ])
            ->headerActions([
                $this->generateScheduleAction(),
            ])
            ->recordActions([
                $this->viewPaymentsAction(),
                $this->recordPaymentAction(),
                $this->markBouncedAction(),
            ])
            ->emptyStateHeading(__('No instalments yet'));
    }

    /**
     * A schedule is generated once for the whole lease — this is a
     * header action, not a per-row create, because there is nothing to fill
     * in per instalment; `InstallmentGenerator` derives every figure itself.
     */
    private function generateScheduleAction(): Action
    {
        return Action::make('generate_schedule')
            ->label(__('Generate Schedule'))
            ->icon('heroicon-o-calendar')
            ->color('primary')
            ->visible(fn (): bool => ! $this->lease()->installments()->exists())
            ->schema([
                TextInput::make('number_of_installments')
                    ->label(__('Number of Instalments'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(48)
                    ->default(12)
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    app(InstallmentGenerator::class)->generateSchedule(
                        $this->lease(),
                        (int) $data['number_of_installments'],
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Could not continue'))
                        ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Something went wrong.'))
                        ->send();

                    return;
                }

                Notification::make()->success()->title(__('Instalment schedule generated.'))->send();
            });
    }

    /**
     * The individual collections behind this instalment's `paid_amount` —
     * a read-only modal, since the ledger itself is never edited directly.
     */
    private function viewPaymentsAction(): Action
    {
        return Action::make('view_payments')
            ->label(__('View Payments'))
            ->icon('heroicon-o-list-bullet')
            ->color('gray')
            ->visible(fn (Installment $record): bool => $record->payments()->exists())
            ->modalContent(fn (Installment $record) => view('filament.pms.leases.installment-payments-modal', [
                'payments' => $record->payments()->orderByDesc('paid_date')->get(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    private function recordPaymentAction(): Action
    {
        return Action::make('record_payment')
            ->label(__('Record Payment'))
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Installment $record): bool => ! $record->isPaid())
            ->schema([
                TextInput::make('amount')
                    ->label(__('Amount (AED)'))
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
                Select::make('payment_method')
                    ->label(__('Payment Method'))
                    ->options(InstallmentPaymentMethod::class),
                TextInput::make('transaction_reference')
                    ->label(__('Transaction / Cheque Reference')),
                TextInput::make('bank_name')
                    ->label(__('Bank Name')),
                DatePicker::make('paid_date')
                    ->label(__('Paid Date'))
                    ->default(now()),
            ])
            ->action(function (Installment $record, array $data): void {
                try {
                    app(PaymentService::class)->recordPayment($record, $data);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Could not continue'))
                        ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Something went wrong.'))
                        ->send();

                    return;
                }

                Notification::make()->success()->title(__('Payment recorded.'))->send();
            });
    }

    private function markBouncedAction(): Action
    {
        return Action::make('mark_bounced')
            ->label(__('Mark Bounced'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('Reverts any amount this instalment had received and adds an administrative penalty.'))
            ->visible(fn (Installment $record): bool => ! $record->isPaid())
            ->action(function (Installment $record): void {
                try {
                    app(PaymentService::class)->markBounced($record);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Could not continue'))
                        ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Something went wrong.'))
                        ->send();

                    return;
                }

                Notification::make()->success()->title(__('Instalment marked bounced.'))->send();
            });
    }
}
