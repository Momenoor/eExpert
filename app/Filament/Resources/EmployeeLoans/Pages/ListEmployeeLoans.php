<?php

namespace App\Filament\Resources\EmployeeLoans\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
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
