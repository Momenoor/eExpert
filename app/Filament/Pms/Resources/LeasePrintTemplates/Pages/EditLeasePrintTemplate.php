<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\Pages;

use App\Filament\Pms\Resources\LeasePrintTemplates\LeasePrintTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeasePrintTemplate extends EditRecord
{
    protected static string $resource = LeasePrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
