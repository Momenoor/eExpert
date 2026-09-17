<?php

namespace App\Filament\Mms\Resources\Types\Pages;

use App\Filament\Mms\Resources\Types\TypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTypes extends ListRecords
{
    protected static string $resource = TypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
