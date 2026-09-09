<?php

namespace App\Filament\Resources\EmployeeLoans\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use App\Models\EmployeeLoan;
use App\Services\LoanScheduleService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LogicException;

/**
 * Editing is closed once payroll has taken an instalment.
 *
 * EmployeeLoanPolicy::update() answers that, and EditRecord authorises on
 * mount, so reaching this page for a part-recovered advance is a 403 rather
 * than a journey that ends in the schedule service throwing. The row action is
 * hidden for those loans, and the view page shows them instead.
 */
class EditEmployeeLoan extends EditRecord
{
    use RefreshesPayrollData;

    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->loan()->isEditable()),
        ];
    }

    /**
     * Changing the amount or the term changes the schedule.
     *
     * The service refuses once payroll has taken an instalment, which is the
     * guard that stops an approved run's deductions being rewritten underneath
     * it.
     */
    protected function afterSave(): void
    {
        app(LoanScheduleService::class)->generateFor($this->loan());

        $this->dispatchPayrollDataUpdated();
    }

    private function loan(): EmployeeLoan
    {
        $record = $this->getRecord();

        if (! $record instanceof EmployeeLoan) {
            throw new LogicException('This page only edits an employee loan.');
        }

        return $record;
    }
}
