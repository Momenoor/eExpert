<?php

namespace App\Filament\Pms\Resources\ConditionTemplates\Pages;

use App\Filament\Pms\Resources\ConditionTemplates\ConditionTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConditionTemplate extends EditRecord
{
    protected static string $resource = ConditionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
