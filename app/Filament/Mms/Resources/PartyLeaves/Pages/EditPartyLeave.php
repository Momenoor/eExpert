<?php

namespace App\Filament\Mms\Resources\PartyLeaves\Pages;

use App\Filament\Mms\Resources\PartyLeaves\PartyLeaveResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartyLeave extends EditRecord
{
    protected static string $resource = PartyLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
