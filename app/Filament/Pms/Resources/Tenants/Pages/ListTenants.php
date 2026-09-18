<?php

namespace App\Filament\Pms\Resources\Tenants\Pages;

use App\Filament\Pms\Imports\TenantImporter;
use App\Filament\Pms\Resources\Tenants\TenantResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(TenantImporter::class)
                ->pluralModelLabel(__('Tenants')),
            CreateAction::make()->label(__('Add Tenant')),
        ];
    }
}
