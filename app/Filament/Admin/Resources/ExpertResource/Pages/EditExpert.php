<?php

namespace App\Filament\Admin\Resources\ExpertResource\Pages;

use App\Filament\Admin\Resources\ExpertResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpert extends EditRecord
{
    protected static string $resource = ExpertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
