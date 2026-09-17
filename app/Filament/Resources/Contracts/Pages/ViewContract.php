<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\PMS\AttestationSystem;
use App\Enums\PMS\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
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

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->attestAction(),
            $this->evaluateRenewalAction(),
            $this->terminateAction(),
        ];
    }

    private function contract(): Contract
    {
        $record = $this->getRecord();

        if (! $record instanceof Contract) {
            throw new LogicException('This page only shows a contract.');
        }

        return $record;
    }

    private function attestAction(): Action
    {
        return Action::make('attest')
            ->label(__('Register Attestation'))
            ->icon('heroicon-o-shield-check')
            ->color('success')
            ->visible(fn (): bool => $this->contract()->status === ContractStatus::PENDING_ATTESTATION)
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
                $this->runContractStep(
                    fn (ContractService $service) => $service->attest($this->contract(), $data),
                    __('Contract attested and marked active.'),
                );
            });
    }

    /**
     * A what-if, not a mutation — nothing about the contract changes here.
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
                    $this->contract(),
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

    private function terminateAction(): Action
    {
        return Action::make('terminate')
            ->label(__('Terminate'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => ! in_array(
                $this->contract()->status,
                [ContractStatus::TERMINATED, ContractStatus::EXPIRED],
                true,
            ))
            ->action(fn () => $this->runContractStep(
                fn (ContractService $service) => $service->terminate($this->contract()),
                __('Contract terminated.'),
            ));
    }

    private function runContractStep(callable $step, string $success): void
    {
        try {
            $step(app(ContractService::class));
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
