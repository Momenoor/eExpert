<?php

namespace App\Filament\Admin\Resources\MatterResource\Pages;

use App\Filament\Admin\Resources\MatterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMatter extends EditRecord
{
    protected static string $resource = MatterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
