<?php

namespace App\Filament\Pms\Resources\Leases\Pages;

use App\Enums\PMS\AttestationSystem;
use App\Enums\PMS\Emirate;
use App\Enums\PMS\LeaseStatus;
use App\Enums\PMS\PropertyClassification;
use App\Filament\Pms\Resources\Leases\LeaseResource;
use App\Models\Lease;
use App\Services\PMS\LeaseService;
use App\Services\PMS\RentReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;
use LogicException;
use RuntimeException;
use Throwable;

class ViewLease extends ViewRecord
{
    protected static string $resource = LeaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->editAction(),
            $this->submitForAttestationAction(),
            $this->attestAction(),
            $this->evaluateRenewalAction(),
            $this->renewAction(),
            $this->terminateAction(),
            $this->printSharjahCommercialAction(),
            $this->printSharjahResidentialAction(),
            $this->printDubaiAction(),
            $this->printTaxInvoicesAction(),
            $this->printReceivableReceiptAction(),
        ];
    }

    private function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('Edit'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => LeaseResource::canEdit($this->lease()))
            ->url(fn (): string => LeaseResource::getUrl('edit', ['record' => $this->lease()]));
    }

    private function submitForAttestationAction(): Action
    {
        return Action::make('submit_for_attestation')
            ->label(__('Submit for Attestation'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->lease()->status === LeaseStatus::DRAFT)
            ->action(fn () => $this->runLeaseStep(
                fn (LeaseService $service) => $service->submitForAttestation($this->lease()),
                __('Lease submitted for attestation.'),
            ));
    }

    private function lease(): Lease
    {
        $record = $this->getRecord();

        if (! $record instanceof Lease) {
            throw new LogicException('This page only shows a lease.');
        }

        return $record;
    }

    private function attestAction(): Action
    {
        return Action::make('attest')
            ->label(__('Register Attestation'))
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (): bool => $this->lease()->status === LeaseStatus::PENDING_ATTESTATION)
            ->schema([
                Select::make('attestation_system')
                    ->label(__('Attestation System'))
                    ->options(AttestationSystem::class)
                    ->required(),
                TextInput::make('attestation_serial_number')
                    ->label(__('Attestation / Ejari Serial Number'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('title_deed_number')
                    ->label(__('Title Deed Number'))
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->runLeaseStep(
                    fn (LeaseService $service) => $service->attest($this->lease(), $data),
                    __('Lease attested and marked active.'),
                );
            });
    }

    /**
     * A what-if, not a mutation — nothing about the lease changes here.
     * `RentReviewService` computes the RERA cap and the 90-day compliance
     * check; this action only collects the two inputs it needs and shows
     * what came back.
     */
    private function evaluateRenewalAction(): Action
    {
        return Action::make('evaluate_renewal')
            ->label(__('Evaluate Renewal'))
            ->icon('heroicon-o-calculator')
            ->color('gray')
            ->schema([
                DatePicker::make('target_renewal_date')
                    ->label(__('Target Renewal Date'))
                    ->default(now())
                    ->required(),
                TextInput::make('market_average_rent')
                    ->label(__('Market Average Rent (AED)'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $evaluation = app(RentReviewService::class)->evaluateRenewal(
                    $this->lease(),
                    Carbon::parse($data['target_renewal_date']),
                    (float) $data['market_average_rent'],
                );

                $notification = Notification::make()
                    ->title(__('Renewal Proposal'))
                    ->body(implode("\n", array_filter([
                        __('Current Rent: :amount AED', ['amount' => number_format($evaluation->currentRent, 2)]),
                        __('Below Market: :percent%', ['percent' => $evaluation->percentBelowMarket]),
                        __('RERA Cap: :percent% increase allowed', ['percent' => $evaluation->allowedIncreasePercent]),
                        __('Max Allowable Rent: :amount AED', ['amount' => number_format($evaluation->maxAllowableRent, 2)]),
                        $evaluation->nonComplianceMessage,
                    ])))
                    ->persistent();

                $evaluation->isWithinNoticeWindow ? $notification->success() : $notification->warning();

                $notification->send();
            });
    }

    /**
     * A real mutation (unlike `evaluateRenewalAction()`'s what-if): creates
     * a new lease record marked `RENEWAL`, links it back to this one, and
     * moves this lease to `RENEWED`.
     */
    private function renewAction(): Action
    {
        return Action::make('renew')
            ->label(__('Renew'))
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->visible(fn (): bool => in_array($this->lease()->status, [LeaseStatus::ACTIVE, LeaseStatus::EXPIRED], true))
            ->schema([
                DatePicker::make('start_date')
                    ->label(__('New Start Date'))
                    ->default(fn (): string => $this->lease()->end_date->addDay()->toDateString())
                    ->required(),
                DatePicker::make('end_date')
                    ->label(__('New End Date'))
                    ->default(fn (): string => $this->lease()->end_date->addYear()->toDateString())
                    ->required()
                    ->afterOrEqual('start_date'),
                TextInput::make('total_base_rent')
                    ->label(__('New Total Base Rent (AED)'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->default(fn (): string => $this->lease()->total_base_rent),
            ])
            ->action(function (array $data) {
                $lease = $this->lease();

                try {
                    $renewal = app(LeaseService::class)->renew($lease, $data);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title(__('Could not continue'))
                        ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Something went wrong.'))
                        ->send();

                    return null;
                }

                Notification::make()->success()->title(__('Lease renewed.'))->send();

                return redirect(static::getResource()::getUrl('view', ['record' => $renewal]));
            });
    }

    private function terminateAction(): Action
    {
        return Action::make('terminate')
            ->label(__('Terminate'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => ! in_array(
                $this->lease()->status,
                [LeaseStatus::TERMINATED, LeaseStatus::EXPIRED],
                true,
            ))
            ->action(fn () => $this->runLeaseStep(
                fn (LeaseService $service) => $service->terminate($this->lease()),
                __('Lease terminated.'),
            ));
    }

    /**
     * True when every unit behind this lease sits in a property located in
     * Sharjah — the only emirate the system currently has a print layout
     * for.
     */
    private function isSharjahLease(): bool
    {
        return $this->lease()->units->pluck('property.emirate')->filter()->isNotEmpty()
            && $this->lease()->units->pluck('property.emirate')->every(fn (?Emirate $emirate): bool => $emirate === Emirate::SHARJAH);
    }

    private function isCommercialLease(): bool
    {
        return $this->lease()->units->pluck('property_classification')->contains(
            fn (?PropertyClassification $classification): bool => in_array(
                $classification,
                [PropertyClassification::COMMERCIAL, PropertyClassification::INDUSTRIAL],
                true,
            ),
        );
    }

    private function printSharjahCommercialAction(): Action
    {
        return Action::make('print_sharjah_commercial')
            ->label(__('Print (Sharjah Commercial)'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (): bool => $this->isSharjahLease() && $this->isCommercialLease())
            ->url(fn (): string => route('pms.leases.print', ['lease' => $this->lease(), 'format' => 'sharjah_commercial']))
            ->openUrlInNewTab();
    }

    private function printSharjahResidentialAction(): Action
    {
        return Action::make('print_sharjah_residential')
            ->label(__('Print (Sharjah Residential)'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (): bool => $this->isSharjahLease() && ! $this->isCommercialLease())
            ->url(fn (): string => route('pms.leases.print', ['lease' => $this->lease(), 'format' => 'sharjah_residential']))
            ->openUrlInNewTab();
    }

    private function isDubaiLease(): bool
    {
        return $this->lease()->units->pluck('property.emirate')->filter()->isNotEmpty()
            && $this->lease()->units->pluck('property.emirate')->every(fn (?Emirate $emirate): bool => $emirate === Emirate::DUBAI);
    }

    private function printDubaiAction(): Action
    {
        return Action::make('print_dubai')
            ->label(__('Print (Dubai)'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (): bool => $this->isDubaiLease())
            ->url(fn (): string => route('pms.leases.print', ['lease' => $this->lease(), 'format' => 'dubai_ejari']))
            ->openUrlInNewTab();
    }

    /**
     * Available as soon as the lease has an instalment to invoice — a
     * renewal is just another lease with its own new instalments, so this
     * needs no extra step for the "on renewal" case either.
     */
    private function printTaxInvoicesAction(): Action
    {
        return Action::make('print_tax_invoices')
            ->label(__('Print Tax Invoices'))
            ->icon('heroicon-o-document-currency-dollar')
            ->color('gray')
            ->visible(fn (): bool => $this->lease()->installments()->exists())
            ->url(fn (): string => route('pms.leases.tax-invoices', ['lease' => $this->lease()]))
            ->openUrlInNewTab();
    }

    private function printReceivableReceiptAction(): Action
    {
        return Action::make('print_receivable_receipt')
            ->label(__('Print Receivable Receipt'))
            ->icon('heroicon-o-receipt-percent')
            ->color('gray')
            ->visible(fn (): bool => $this->lease()->installments()->exists())
            ->url(fn (): string => route('pms.leases.receivable-receipt', ['lease' => $this->lease()]))
            ->openUrlInNewTab();
    }

    private function runLeaseStep(callable $step, string $success): void
    {
        try {
            $step(app(LeaseService::class));
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title(__('Could not continue'))
                ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Something went wrong.'))
                ->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }
}
