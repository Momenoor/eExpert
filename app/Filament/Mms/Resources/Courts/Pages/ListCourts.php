<?php

namespace App\Filament\Mms\Resources\Courts\Pages;

use App\Filament\Mms\Resources\Courts\CourtResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourts extends ListRecords
{
    protected static string $resource = CourtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
