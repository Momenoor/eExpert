<?php

namespace App\Filament\Pms\Resources\OwnerProfiles\Pages;

use App\Filament\Pms\Resources\OwnerProfiles\OwnerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOwnerProfiles extends ListRecords
{
    protected static string $resource = OwnerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Add Owner')),
        ];
    }
}
