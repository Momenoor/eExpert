<?php

namespace App\Filament\Resources\EmployeeProfiles\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Imports\EmployeeProfileImporter;
use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeProfiles extends ListRecords
{
    use RefreshesPayrollData;

    protected static string $resource = EmployeeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(EmployeeProfileImporter::class)
                ->pluralModelLabel(__('Employees')),
            CreateAction::make(),
        ];
    }
}
