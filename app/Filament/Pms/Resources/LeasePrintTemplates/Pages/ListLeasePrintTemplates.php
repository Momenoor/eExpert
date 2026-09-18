<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\Pages;

use App\Filament\Pms\Resources\LeasePrintTemplates\LeasePrintTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeasePrintTemplates extends ListRecords
{
    protected static string $resource = LeasePrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
