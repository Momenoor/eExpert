<?php

namespace App\Filament\Resources\PayrollRuns\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\PayrollRuns\PayrollRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollRuns extends ListRecords
{
    use RefreshesPayrollData;

    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('New Payroll Run')),
        ];
    }
}
