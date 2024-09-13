<?php

namespace App\Filament\Admin\Resources\CourtLevelResource\Pages;

use App\Filament\Admin\Resources\CourtLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourtLevel extends EditRecord
{
    protected static string $resource = CourtLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
