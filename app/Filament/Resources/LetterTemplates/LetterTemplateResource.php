<?php

namespace App\Filament\Resources\LetterTemplates;

use App\Filament\Resources\LetterTemplates\Pages\CreateLetterTemplate;
use App\Filament\Resources\LetterTemplates\Pages\EditLetterTemplate;
use App\Filament\Resources\LetterTemplates\Pages\ListLetterTemplates;
use App\Filament\Resources\LetterTemplates\Pages\ViewLetterTemplate;
use App\Filament\Resources\LetterTemplates\Schemas\LetterTemplateForm;
use App\Filament\Resources\LetterTemplates\Schemas\LetterTemplateInfolist;
use App\Filament\Resources\LetterTemplates\Tables\LetterTemplatesTable;
use App\Models\LetterTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LetterTemplateResource extends Resource
{
    protected static ?string $model = LetterTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LetterTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LetterTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LetterTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLetterTemplates::route('/'),
            'create' => CreateLetterTemplate::route('/create'),
            'view' => ViewLetterTemplate::route('/{record}'),
            'edit' => EditLetterTemplate::route('/{record}/edit'),
        ];
    }
}
