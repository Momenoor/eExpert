<?php

namespace App\Filament\Pms\Resources\OwnerGroups;

use App\Filament\Pms\Resources\OwnerGroups\Pages\CreateOwnerGroup;
use App\Filament\Pms\Resources\OwnerGroups\Pages\EditOwnerGroup;
use App\Filament\Pms\Resources\OwnerGroups\Pages\ListOwnerGroups;
use App\Filament\Pms\Resources\OwnerGroups\Schemas\OwnerGroupForm;
use App\Filament\Pms\Resources\OwnerGroups\Tables\OwnerGroupsTable;
use App\Models\OwnerGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A named collective ("Legal Heirs of Mahmoud Kalbat") that several owner
 * profiles can belong to — `Property::landlordName()` shows the group's name
 * on a contract instead of listing every member when they all share one.
 */
class OwnerGroupResource extends Resource
{
    protected static ?string $model = OwnerGroup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Ownership';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('Owner Group');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Owner Groups');
    }

    public static function getNavigationLabel(): string
    {
        return __('Owner Groups');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Ownership');
    }

    public static function form(Schema $schema): Schema
    {
        return OwnerGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OwnerGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOwnerGroups::route('/'),
            'create' => CreateOwnerGroup::route('/create'),
            'edit' => EditOwnerGroup::route('/{record}/edit'),
        ];
    }
}
