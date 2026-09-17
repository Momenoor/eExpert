<?php

namespace App\Filament\Resources\TenantProfiles\Pages;

use App\Filament\Resources\TenantProfiles\TenantProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantProfiles extends ListRecords
{
    protected static string $resource = TenantProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Add Tenant')),
        ];
    }
}
