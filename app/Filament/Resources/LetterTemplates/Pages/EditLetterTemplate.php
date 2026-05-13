<?php

namespace App\Filament\Resources\LetterTemplates\Pages;

use App\Filament\Resources\LetterTemplates\LetterTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLetterTemplate extends EditRecord
{
    protected static string $resource = LetterTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
