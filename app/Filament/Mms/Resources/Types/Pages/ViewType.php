<?php

namespace App\Filament\Mms\Resources\Types\Pages;

use App\Filament\Mms\Resources\Types\TypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewType extends ViewRecord
{
    protected static string $resource = TypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
