<?php

namespace App\Filament\Pms\Resources\OwnerProfiles\Pages;

use App\Filament\Pms\Imports\OwnerProfileImporter;
use App\Filament\Pms\Resources\OwnerProfiles\OwnerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListOwnerProfiles extends ListRecords
{
    protected static string $resource = OwnerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(OwnerProfileImporter::class)
                ->pluralModelLabel(__('Owners')),
            CreateAction::make()->label(__('Add Owner')),
        ];
    }
}
