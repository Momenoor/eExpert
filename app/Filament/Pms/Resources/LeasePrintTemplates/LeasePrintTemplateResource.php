<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates;

use App\Filament\Pms\Resources\LeasePrintTemplates\Pages\CreateLeasePrintTemplate;
use App\Filament\Pms\Resources\LeasePrintTemplates\Pages\EditLeasePrintTemplate;
use App\Filament\Pms\Resources\LeasePrintTemplates\Pages\ListLeasePrintTemplates;
use App\Filament\Pms\Resources\LeasePrintTemplates\RelationManagers\PagesRelationManager;
use App\Filament\Pms\Resources\LeasePrintTemplates\Schemas\LeasePrintTemplateForm;
use App\Filament\Pms\Resources\LeasePrintTemplates\Tables\LeasePrintTemplatesTable;
use App\Models\LeasePrintTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The image-background pages a lease's print route renders — see
 * `LeasePrintFieldResolver` for what each placed field actually shows, and
 * `App\Livewire\Pms\PrintTemplatePageBuilder` for the click-to-place tool
 * used to position them on an uploaded page.
 */
class LeasePrintTemplateResource extends Resource
{
    protected static ?string $model = LeasePrintTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Templates';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('Print Template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Print Templates');
    }

    public static function getNavigationLabel(): string
    {
        return __('Print Templates');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Templates');
    }

    public static function form(Schema $schema): Schema
    {
        return LeasePrintTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeasePrintTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeasePrintTemplates::route('/'),
            'create' => CreateLeasePrintTemplate::route('/create'),
            'edit' => EditLeasePrintTemplate::route('/{record}/edit'),
        ];
    }
}
