<?php

namespace App\Filament\Resources\PayrollRuns;

use App\Filament\Resources\PayrollRuns\Pages\CreatePayrollRun;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Filament\Resources\PayrollRuns\RelationManagers\PayslipsRelationManager;
use App\Filament\Resources\PayrollRuns\Schemas\PayrollRunForm;
use App\Filament\Resources\PayrollRuns\Schemas\PayrollRunInfolist;
use App\Filament\Resources\PayrollRuns\Tables\PayrollRunsTable;
use App\Models\PayrollRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    public static function getModelLabel(): string
    {
        return __('Payroll Run');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payroll Runs');
    }

    public static function getNavigationLabel(): string
    {
        return __('Payroll Runs');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Financial');
    }

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'period';

    public static function form(Schema $schema): Schema
    {
        return PayrollRunForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayrollRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PayslipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayrollRuns::route('/'),
            'create' => CreatePayrollRun::route('/create'),
            'view' => ViewPayrollRun::route('/{record}'),
        ];
    }
}
