<?php

namespace App\Filament\Mms\Resources\Courts\Pages;

use App\Filament\Mms\Resources\Courts\CourtResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourt extends ViewRecord
{
    protected static string $resource = CourtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
