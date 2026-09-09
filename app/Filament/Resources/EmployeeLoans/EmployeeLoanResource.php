<?php

namespace App\Filament\Resources\EmployeeLoans;

use App\Filament\Resources\EmployeeLoans\Pages\CreateEmployeeLoan;
use App\Filament\Resources\EmployeeLoans\Pages\EditEmployeeLoan;
use App\Filament\Resources\EmployeeLoans\Pages\ListEmployeeLoans;
use App\Filament\Resources\EmployeeLoans\Pages\ViewEmployeeLoan;
use App\Filament\Resources\EmployeeLoans\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\EmployeeLoans\Schemas\EmployeeLoanForm;
use App\Filament\Resources\EmployeeLoans\Schemas\EmployeeLoanInfolist;
use App\Filament\Resources\EmployeeLoans\Tables\EmployeeLoansTable;
use App\Models\EmployeeLoan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class EmployeeLoanResource extends Resource
{
    protected static ?string $model = EmployeeLoan::class;

    public static function getModelLabel(): string
    {
        return __('Loan / Petty Cash');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Loans & Petty Cash');
    }

    public static function getNavigationLabel(): string
    {
        return __('Loans & Petty Cash');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Financial');
    }

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return EmployeeLoanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeLoanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeLoansTable::configure($table);
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
            'index' => ListEmployeeLoans::route('/'),
            'create' => CreateEmployeeLoan::route('/create'),
            'view' => ViewEmployeeLoan::route('/{record}'),
            'edit' => EditEmployeeLoan::route('/{record}/edit'),
        ];
    }
}
