<?php

namespace App\Filament\Pms\Resources\Tenants;

use App\Filament\Pms\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Pms\Resources\Tenants\Pages\EditTenant;
use App\Filament\Pms\Resources\Tenants\Pages\ListTenants;
use App\Filament\Pms\Resources\Tenants\Schemas\TenantForm;
use App\Filament\Pms\Resources\Tenants\Tables\TenantsTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Leasing';

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
        return __('Leasing');
    }

    public static function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
