<?php

namespace App\Filament\Pms\Resources\Properties\Pages;

use App\Filament\Pms\Imports\PropertyImporter;
use App\Filament\Pms\Resources\Properties\PropertyResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(PropertyImporter::class)
                ->pluralModelLabel(__('Properties')),
            CreateAction::make()->label(__('New Property')),
        ];
    }
}
