<?php

namespace App\Filament\Admin\Resources\MatterTypeResource\Pages;

use App\Filament\Admin\Resources\MatterTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMatterTypes extends ListRecords
{
    protected static string $resource = MatterTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
