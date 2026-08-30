<?php

namespace App\Filament\Resources\Incentive\IncentiveExtraRules;

use App\Filament\Resources\Incentive\IncentiveExtraRules\Pages\CreateIncentiveExtraRules;
use App\Filament\Resources\Incentive\IncentiveExtraRules\Pages\EditIncentiveExtraRules;
use App\Filament\Resources\Incentive\IncentiveExtraRules\Pages\ListIncentiveExtraRules;
use App\Filament\Resources\Incentive\IncentiveExtraRules\Schemas\IncentiveExtraRulesForm;
use App\Filament\Resources\Incentive\IncentiveExtraRules\Tables\IncentiveExtraRulesTable;
use App\Models\IncentiveExtraRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IncentiveExtraRulesResource extends Resource
{
    protected static ?string $model = IncentiveExtraRule::class;

    public static function getModelLabel(): string
    {
        return __('Extra % Rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Extra % Rules');
    }

    public static function getNavigationLabel(): string
    {
        return __('Extra % Rules');
    }

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

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'extra_percentage';

    public static function form(Schema $schema): Schema
    {
        return IncentiveExtraRulesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncentiveExtraRulesTable::configure($table);
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
            'index' => ListIncentiveExtraRules::route('/'),
            'create' => CreateIncentiveExtraRules::route('/create'),
            'edit' => EditIncentiveExtraRules::route('/{record}/edit'),
        ];
    }
}
