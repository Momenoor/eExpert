<?php

namespace App\Filament\Mms\Resources\Types\Pages;

use App\Filament\Mms\Resources\Types\TypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditType extends EditRecord
{
    protected static string $resource = TypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
