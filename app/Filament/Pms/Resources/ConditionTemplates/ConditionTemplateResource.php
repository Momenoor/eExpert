<?php

namespace App\Filament\Pms\Resources\ConditionTemplates;

use App\Filament\Pms\Resources\ConditionTemplates\Pages\CreateConditionTemplate;
use App\Filament\Pms\Resources\ConditionTemplates\Pages\EditConditionTemplate;
use App\Filament\Pms\Resources\ConditionTemplates\Pages\ListConditionTemplates;
use App\Filament\Pms\Resources\ConditionTemplates\RelationManagers\ItemsRelationManager;
use App\Filament\Pms\Resources\ConditionTemplates\Schemas\ConditionTemplateForm;
use App\Filament\Pms\Resources\ConditionTemplates\Tables\ConditionTemplatesTable;
use App\Models\ConditionTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A reusable, selectable set of Special Conditions clauses attached to a
 * lease — see `ConditionTemplate`'s own docblock for why only Special
 * Conditions (not General Conditions) are managed here.
 */
class ConditionTemplateResource extends Resource
{
    protected static ?string $model = ConditionTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Templates';

    protected static ?int $navigationSort = 7;

    public static function getModelLabel(): string
    {
        return __('Conditions Template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Conditions Templates');
    }

    public static function getNavigationLabel(): string
    {
        return __('Conditions Templates');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Templates');
    }

    public static function form(Schema $schema): Schema
    {
        return ConditionTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConditionTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConditionTemplates::route('/'),
            'create' => CreateConditionTemplate::route('/create'),
            'edit' => EditConditionTemplate::route('/{record}/edit'),
        ];
    }
}
