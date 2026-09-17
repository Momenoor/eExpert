<?php

namespace App\Filament\Mms\Resources\LetterTemplates\Pages;

use App\Filament\Mms\Resources\LetterTemplates\LetterTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLetterTemplate extends ViewRecord
{
    protected static string $resource = LetterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
