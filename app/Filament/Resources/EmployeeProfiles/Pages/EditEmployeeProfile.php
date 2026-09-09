<?php

namespace App\Filament\Resources\EmployeeProfiles\Pages;

use App\Filament\Concerns\RefreshesPayrollData;
use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeProfile extends EditRecord
{
    use RefreshesPayrollData;

    protected static string $resource = EmployeeProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
