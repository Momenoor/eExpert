<?php

namespace App\Filament\Mms\Resources\PayrollRuns\Pages;

use App\Filament\Mms\Concerns\RefreshesPayrollData;
use App\Filament\Mms\Resources\PayrollRuns\PayrollRunResource;
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
