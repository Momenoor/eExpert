<?php

namespace App\Filament\Resources\BulkMailCampaignResource;

use App\Filament\Resources\BulkMailCampaignResource\Actions\ImportRecipientsAction;
use App\Filament\Resources\BulkMailCampaignResource\Schema\BulkMailCampaignSchema;
use App\Filament\Resources\BulkMailCampaignResource\Tables\BulkMailCampaignTable;
use App\Models\BulkMailCampaign;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BulkMailCampaignResource extends Resource
{
    protected static ?string $model = BulkMailCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    public static function getNavigationLabel(): string
    {
        return __('bulk_mail.navigation_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bulk_mail.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('bulk_mail.model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return BulkMailCampaignSchema::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BulkMailCampaignTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBulkMailCampaigns::route('/'),
            'create' => Pages\CreateBulkMailCampaign::route('/create'),
            'view' => Pages\ViewBulkMailCampaign::route('/{record}'),
            'edit' => Pages\EditBulkMailCampaign::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
