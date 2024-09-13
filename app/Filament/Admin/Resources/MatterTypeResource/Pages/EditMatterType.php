<?php

namespace App\Filament\Admin\Resources\MatterTypeResource\Pages;

use App\Filament\Admin\Resources\MatterTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMatterType extends EditRecord
{
    protected static string $resource = MatterTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
