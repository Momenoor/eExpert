<?php

namespace App\Filament\Resources\Courts;

use App\Filament\Resources\Courts\Pages\CreateCourt;
use App\Filament\Resources\Courts\Pages\EditCourt;
use App\Filament\Resources\Courts\Pages\ListCourts;
use App\Filament\Resources\Courts\Pages\ViewCourt;
use App\Filament\Resources\Courts\RelationManagers\MattersRelationManager;
use App\Filament\Resources\Courts\Schemas\CourtForm;
use App\Filament\Resources\Courts\Schemas\CourtInfolist;
use App\Filament\Resources\Courts\Tables\CourtsTable;
use App\Models\Court;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CourtResource extends Resource
{
    protected static ?string $model = Court::class;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Court');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Courts');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CourtForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourtInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourtsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MattersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourts::route('/'),
            'create' => CreateCourt::route('/create'),
            'view' => ViewCourt::route('/{record}'),
            'edit' => EditCourt::route('/{record}/edit'),
        ];
    }
}
