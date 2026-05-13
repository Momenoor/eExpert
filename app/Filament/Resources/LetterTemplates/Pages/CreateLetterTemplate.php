<?php

namespace App\Filament\Resources\LetterTemplates\Pages;

use App\Filament\Resources\LetterTemplates\LetterTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLetterTemplate extends CreateRecord
{
    protected static string $resource = LetterTemplateResource::class;
}
