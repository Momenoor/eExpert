<?php

namespace App\Filament\Admin\Resources\CourtLevelResource\Pages;

use App\Filament\Admin\Resources\CourtLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCourtLevels extends ListRecords
{
    protected static string $resource = CourtLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
