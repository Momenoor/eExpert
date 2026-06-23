<?php

namespace App\Filament\Resources\Types;

use App\Filament\Resources\Types\Pages\CreateType;
use App\Filament\Resources\Types\Pages\EditType;
use App\Filament\Resources\Types\Pages\ListTypes;
use App\Filament\Resources\Types\Pages\ViewType;
use App\Filament\Resources\Types\RelationManagers\FieldDefinitionsRelationManager;
use App\Filament\Resources\Types\Schemas\TypeForm;
use App\Filament\Resources\Types\Schemas\TypeInfolist;
use App\Filament\Resources\Types\Tables\TypesTable;
use App\Models\Type;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TypeResource extends Resource
{
    protected static ?string $model = Type::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function getModelLabel(): string
    {
        return __('Matter Type');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Matter Types');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FieldDefinitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTypes::route('/'),
            'create' => CreateType::route('/create'),
            'view' => ViewType::route('/{record}'),
            'edit' => EditType::route('/{record}/edit'),
        ];
    }
}
