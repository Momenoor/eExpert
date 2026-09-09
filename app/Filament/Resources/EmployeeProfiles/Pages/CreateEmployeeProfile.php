<?php

namespace App\Filament\Resources\EmployeeProfiles\Pages;

use App\Filament\Resources\EmployeeProfiles\EmployeeProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeProfile extends CreateRecord
{
    protected static string $resource = EmployeeProfileResource::class;
}
