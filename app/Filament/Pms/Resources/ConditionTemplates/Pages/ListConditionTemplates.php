<?php

namespace App\Filament\Pms\Resources\ConditionTemplates\Pages;

use App\Filament\Pms\Resources\ConditionTemplates\ConditionTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConditionTemplates extends ListRecords
{
    protected static string $resource = ConditionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
