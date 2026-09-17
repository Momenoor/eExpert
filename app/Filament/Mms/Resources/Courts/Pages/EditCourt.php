<?php

namespace App\Filament\Mms\Resources\Courts\Pages;

use App\Filament\Mms\Resources\Courts\CourtResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCourt extends EditRecord
{
    protected static string $resource = CourtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
