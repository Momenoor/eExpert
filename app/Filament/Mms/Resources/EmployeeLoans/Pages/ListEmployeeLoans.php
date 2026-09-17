<?php

namespace App\Filament\Mms\Resources\EmployeeLoans\Pages;

use App\Filament\Mms\Concerns\RefreshesPayrollData;
use App\Filament\Mms\Resources\EmployeeLoans\EmployeeLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeLoans extends ListRecords
{
    use RefreshesPayrollData;

    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New Advance')),
        ];
    }
}
