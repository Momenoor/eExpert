<?php

namespace App\Filament\Resources\EmployeeLoans\Pages;

use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use App\Models\EmployeeLoan;
use App\Services\LoanScheduleService;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeLoan extends CreateRecord
{
    protected static string $resource = EmployeeLoanResource::class;

    /**
     * Build the schedule as soon as the advance exists.
     *
     * A loan with no instalments deducts nothing, and nothing on screen would
     * say so — it would simply sit there being ignored by every payroll run.
     */
    protected function afterCreate(): void
    {
        /** @var EmployeeLoan $loan */
        $loan = $this->getRecord();

        app(LoanScheduleService::class)->generateFor($loan);
    }

    /**
     * Open the advance rather than the list — the schedule is already built.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
