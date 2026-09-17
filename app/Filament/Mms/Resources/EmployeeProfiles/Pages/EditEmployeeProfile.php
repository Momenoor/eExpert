<?php

namespace App\Filament\Mms\Resources\EmployeeProfiles\Pages;

use App\Filament\Mms\Concerns\RefreshesPayrollData;
use App\Filament\Mms\Resources\EmployeeProfiles\EmployeeProfileResource;
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
