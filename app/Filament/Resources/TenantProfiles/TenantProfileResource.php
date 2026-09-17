<?php

namespace App\Filament\Resources\TenantProfiles;

use App\Filament\Resources\TenantProfiles\Pages\CreateTenantProfile;
use App\Filament\Resources\TenantProfiles\Pages\EditTenantProfile;
use App\Filament\Resources\TenantProfiles\Pages\ListTenantProfiles;
use App\Filament\Resources\TenantProfiles\Schemas\TenantProfileForm;
use App\Filament\Resources\TenantProfiles\Tables\TenantProfilesTable;
use App\Models\TenantProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class TenantProfileResource extends Resource
{
    protected static ?string $model = TenantProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Property Management';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Tenant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tenants');
    }

    public static function getNavigationLabel(): string
    {
        return __('Tenants');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Property Management');
    }

    public static function form(Schema $schema): Schema
    {
        return TenantProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantProfiles::route('/'),
            'create' => CreateTenantProfile::route('/create'),
            'edit' => EditTenantProfile::route('/{record}/edit'),
        ];
    }
}
