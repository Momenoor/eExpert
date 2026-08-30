<?php

namespace App\Filament\Resources\Incentive\IncentiveMetaAdjustments;

use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Pages\CreateIncentiveMetaAdjustment;
use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Pages\EditIncentiveMetaAdjustment;
use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Pages\ListIncentiveMetaAdjustments;
use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Schemas\IncentiveMetaAdjustmentForm;
use App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Tables\IncentiveMetaAdjustmentsTable;
use App\Models\IncentiveMetaAdjustment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IncentiveMetaAdjustmentResource extends Resource
{
    protected static ?string $model = IncentiveMetaAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    /**
     * Managed from the consolidated "Incentive Configuration" page instead —
     * this resource's routes stay functional (linked to from there) but it
     * no longer needs its own sidebar entry.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('Incentive Adjustment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Incentive Adjustments');
    }

    public static function form(Schema $schema): Schema
    {
        return IncentiveMetaAdjustmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncentiveMetaAdjustmentsTable::configure($table);
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
            'index' => ListIncentiveMetaAdjustments::route('/'),
            'create' => CreateIncentiveMetaAdjustment::route('/create'),
            'edit' => EditIncentiveMetaAdjustment::route('/{record}/edit'),
        ];
    }
}
