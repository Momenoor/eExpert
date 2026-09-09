<?php

namespace App\Filament\Resources\EmployeeProfiles;

use App\Filament\Resources\EmployeeProfiles\Pages\CreateEmployeeProfile;
use App\Filament\Resources\EmployeeProfiles\Pages\EditEmployeeProfile;
use App\Filament\Resources\EmployeeProfiles\Pages\ListEmployeeProfiles;
use App\Filament\Resources\EmployeeProfiles\RelationManagers\SalaryComponentsRelationManager;
use App\Filament\Resources\EmployeeProfiles\Schemas\EmployeeProfileForm;
use App\Filament\Resources\EmployeeProfiles\Tables\EmployeeProfilesTable;
use App\Models\EmployeeProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class EmployeeProfileResource extends Resource
{
    protected static ?string $model = EmployeeProfile::class;

    public static function getModelLabel(): string
    {
        return __('Employee');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Employees');
    }

    public static function getNavigationLabel(): string
    {
        return __('Employees');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Human Resources');
    }

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'employee_no';

    public static function form(Schema $schema): Schema
    {
        return EmployeeProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SalaryComponentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeProfiles::route('/'),
            'create' => CreateEmployeeProfile::route('/create'),
            'edit' => EditEmployeeProfile::route('/{record}/edit'),
        ];
    }
}
