<?php

namespace App\Filament\Admin\Resources\CourtLevelResource\Pages;

use App\Filament\Admin\Resources\CourtLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCourtLevel extends ViewRecord
{
    protected static string $resource = CourtLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
