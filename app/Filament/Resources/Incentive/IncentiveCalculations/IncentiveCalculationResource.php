<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations;

use App\Filament\Resources\Incentive\IncentiveCalculations\Pages\CreateIncentiveCalculation;
use App\Filament\Resources\Incentive\IncentiveCalculations\Pages\EditIncentiveCalculation;
use App\Filament\Resources\Incentive\IncentiveCalculations\Pages\ListIncentiveCalculations;
use App\Filament\Resources\Incentive\IncentiveCalculations\Pages\ViewIncentiveCalculation;
use App\Filament\Resources\Incentive\IncentiveCalculations\Schemas\IncentiveCalculationForm;
use App\Filament\Resources\Incentive\IncentiveCalculations\Schemas\IncentiveCalculationInfolist;
use App\Filament\Resources\Incentive\IncentiveCalculations\Tables\IncentiveCalculationsTable;
use App\Models\IncentiveCalculation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IncentiveCalculationResource extends Resource
{
    protected static ?string $model = IncentiveCalculation::class;

    protected static ?int $navigationSort = 1;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Incentive Calculation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Incentive Calculations');
    }

    public static function getNavigationLabel(): string
    {
        return __('Incentive Calculations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    public static function form(Schema $schema): Schema
    {
        return IncentiveCalculationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IncentiveCalculationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncentiveCalculationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncentiveCalculations::route('/'),
            'create' => CreateIncentiveCalculation::route('/create'),
            'view' => ViewIncentiveCalculation::route('/{record}'),
            'edit' => EditIncentiveCalculation::route('/{record}/edit'),
        ];
    }
}
