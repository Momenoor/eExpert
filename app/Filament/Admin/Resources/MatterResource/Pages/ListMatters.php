<?php

namespace App\Filament\Admin\Resources\MatterResource\Pages;

use App\Filament\Admin\Resources\MatterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMatters extends ListRecords
{
    protected static string $resource = MatterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
