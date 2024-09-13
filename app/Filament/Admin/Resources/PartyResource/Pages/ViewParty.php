<?php

namespace App\Filament\Admin\Resources\PartyResource\Pages;

use App\Filament\Admin\Resources\PartyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParty extends ViewRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
