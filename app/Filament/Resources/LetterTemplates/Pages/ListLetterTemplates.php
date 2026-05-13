<?php

namespace App\Filament\Resources\LetterTemplates\Pages;

use App\Filament\Resources\LetterTemplates\LetterTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLetterTemplates extends ListRecords
{
    protected static string $resource = LetterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
