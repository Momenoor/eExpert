<?php

namespace App\Filament\Mms\Resources\Parties\Pages;

use App\Filament\Mms\Resources\Parties\PartyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewParty extends ViewRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
