<?php

namespace App\Filament\Resources\OwnerGroups\Pages;

use App\Filament\Resources\OwnerGroups\OwnerGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOwnerGroups extends ListRecords
{
    protected static string $resource = OwnerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
