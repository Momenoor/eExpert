<?php

namespace App\Filament\Pms\Resources\Leases;

use App\Filament\Pms\Resources\Leases\Pages\CreateLease;
use App\Filament\Pms\Resources\Leases\Pages\EditLease;
use App\Filament\Pms\Resources\Leases\Pages\ListLeases;
use App\Filament\Pms\Resources\Leases\Pages\ViewLease;
use App\Filament\Pms\Resources\Leases\RelationManagers\InstallmentsRelationManager;
use App\Filament\Pms\Resources\Leases\Schemas\LeaseForm;
use App\Filament\Pms\Resources\Leases\Schemas\LeaseInfolist;
use App\Filament\Pms\Resources\Leases\Tables\LeasesTable;
use App\Models\Lease;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LeaseResource extends Resource
{
    protected static ?string $model = Lease::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Leasing';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Lease');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Leases');
    }

    public static function getNavigationLabel(): string
    {
        return __('Leases');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Leasing');
    }

    public static function form(Schema $schema): Schema
    {
        return LeaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeases::route('/'),
            'create' => CreateLease::route('/create'),
            'view' => ViewLease::route('/{record}'),
            'edit' => EditLease::route('/{record}/edit'),
        ];
    }

    /**
     * A lease stops being a correctable draft once it's submitted for
     * attestation — see `Lease::isEditable()`.
     */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record instanceof Lease && $record->isEditable();
    }
}
