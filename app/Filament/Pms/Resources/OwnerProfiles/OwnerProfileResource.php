<?php

namespace App\Filament\Pms\Resources\OwnerProfiles;

use App\Filament\Pms\Resources\OwnerProfiles\Pages\CreateOwnerProfile;
use App\Filament\Pms\Resources\OwnerProfiles\Pages\EditOwnerProfile;
use App\Filament\Pms\Resources\OwnerProfiles\Pages\ListOwnerProfiles;
use App\Filament\Pms\Resources\OwnerProfiles\Schemas\OwnerProfileForm;
use App\Filament\Pms\Resources\OwnerProfiles\Tables\OwnerProfilesTable;
use App\Models\OwnerProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OwnerProfileResource extends Resource
{
    protected static ?string $model = OwnerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Ownership';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('Owner');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Owners');
    }

    public static function getNavigationLabel(): string
    {
        return __('Owners');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Ownership');
    }

    public static function form(Schema $schema): Schema
    {
        return OwnerProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OwnerProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOwnerProfiles::route('/'),
            'create' => CreateOwnerProfile::route('/create'),
            'edit' => EditOwnerProfile::route('/{record}/edit'),
        ];
    }
}
