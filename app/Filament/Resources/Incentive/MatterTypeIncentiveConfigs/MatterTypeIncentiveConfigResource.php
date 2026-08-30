<?php

namespace App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs;

use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Pages\CreateMatterTypeIncentiveConfig;
use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Pages\EditMatterTypeIncentiveConfig;
use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Pages\ListMatterTypeIncentiveConfigs;
use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Pages\ViewMatterTypeIncentiveConfig;
use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Schemas\MatterTypeIncentiveConfigForm;
use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Schemas\MatterTypeIncentiveConfigInfolist;
use App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Tables\MatterTypeIncentiveConfigsTable;
use App\Models\MatterTypeIncentiveConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MatterTypeIncentiveConfigResource extends Resource
{
    protected static ?string $model = MatterTypeIncentiveConfig::class;

    public static function getModelLabel(): string
    {
        return __('Incentive Config');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Incentive Configs');
    }

    public static function getNavigationLabel(): string
    {
        return __('Incentive Config');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'calculation_type';

    public static function form(Schema $schema): Schema
    {
        return MatterTypeIncentiveConfigForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MatterTypeIncentiveConfigInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MatterTypeIncentiveConfigsTable::configure($table);
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
            'index' => ListMatterTypeIncentiveConfigs::route('/'),
            'create' => CreateMatterTypeIncentiveConfig::route('/create'),
            'view' => ViewMatterTypeIncentiveConfig::route('/{record}'),
            'edit' => EditMatterTypeIncentiveConfig::route('/{record}/edit'),
        ];
    }
}
