<?php

namespace App\Filament\Resources\LetterTemplates\Schemas;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LetterTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('subject'),
                TextEntry::make('body'),
                TextEntry::make('locale'),
                IconEntry::make('is_active')
                    ->boolean(),
                IconEntry::make('is_default')
                    ->boolean(),
                TextEntry::make('category')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
