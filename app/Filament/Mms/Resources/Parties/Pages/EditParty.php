<?php

namespace App\Filament\Mms\Resources\Parties\Pages;

use App\Filament\Mms\Resources\Parties\PartyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditParty extends EditRecord
{
    protected static string $resource = PartyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
