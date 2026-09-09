<?php

namespace App\Filament\Resources\EmployeeProfiles\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeProfiles extends ListRecords
{
    use RefreshesPayrollData;

    protected static string $resource = EmployeeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
