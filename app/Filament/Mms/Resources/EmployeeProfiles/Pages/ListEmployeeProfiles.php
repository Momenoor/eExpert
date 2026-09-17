<?php

namespace App\Filament\Mms\Resources\EmployeeProfiles\Pages;

use App\Filament\Mms\Concerns\RefreshesPayrollData;
use App\Filament\Mms\Imports\EmployeeProfileImporter;
use App\Filament\Mms\Resources\EmployeeProfiles\EmployeeProfileResource;
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
