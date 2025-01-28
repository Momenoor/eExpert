<?php

namespace App\Filament\Admin\Resources\ExpertTypeResource\Pages;

use App\Filament\Admin\Resources\ExpertTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpertTypes extends ListRecords
{
    protected static string $resource = ExpertTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
