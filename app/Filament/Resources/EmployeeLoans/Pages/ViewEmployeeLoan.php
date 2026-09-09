<?php

namespace App\Filament\Resources\EmployeeLoans\Pages;

use App\Filament\Concerns\PayrollRefresh;
use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use App\Models\EmployeeLoan;
use App\Services\LoanScheduleService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use LogicException;

/**
 * A read-only view of an advance and its schedule.
 *
 * Editing closes as soon as payroll takes an instalment, and before this page
 * existed that left a part-recovered loan with nowhere to look: the row action
 * was hidden and the edit page bounced you back to the list. The instalments are
 * the record most worth reading, and they outlive the right to change them.
 */
class ViewEmployeeLoan extends ViewRecord
{
    use RefreshesPayrollData;

    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => $this->loan()->isEditable()),

            Action::make('generate_schedule')
                ->label(__('Build Schedule'))
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('Replaces the existing instalments. Only possible while nothing has been deducted.'))
                ->authorize('update')
                ->visible(fn (): bool => $this->loan()->isEditable())
                ->action(function (): void {
                    $schedule = app(LoanScheduleService::class)->generateFor($this->loan());

                    Notification::make()
                        ->success()
                        ->title(__('Schedule built'))
                        ->body(__(':count instalments created.', ['count' => count($schedule)]))
                        ->send();

                    $this->dispatch(PayrollRefresh::EVENT);
                }),
        ];
    }

    private function loan(): EmployeeLoan
    {
        $record = $this->getRecord();

        if (! $record instanceof EmployeeLoan) {
            throw new LogicException('This page only shows an employee loan.');
        }

        return $record;
    }
}
