<?php

namespace App\Filament\Mms\Resources\BulkMailCampaigns;

use App\Filament\Concerns\HasModuleGate;
use App\Filament\Mms\Resources\BulkMailCampaigns\Pages\CreateBulkMailCampaign;
use App\Filament\Mms\Resources\BulkMailCampaigns\Pages\EditBulkMailCampaign;
use App\Filament\Mms\Resources\BulkMailCampaigns\Pages\ListBulkMailCampaigns;
use App\Filament\Mms\Resources\BulkMailCampaigns\Pages\ViewBulkMailCampaign;
use App\Filament\Mms\Resources\BulkMailCampaigns\RelationManagers\RecipientsRelationManager;
use App\Filament\Mms\Resources\BulkMailCampaigns\Schema\BulkMailCampaignSchema;
use App\Filament\Mms\Resources\BulkMailCampaigns\Tables\BulkMailCampaignTable;
use App\Models\BulkMailCampaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BulkMailCampaignResource extends Resource
{
    use HasModuleGate;

    protected static ?string $model = BulkMailCampaign::class;

    public static function moduleGateKey(): string
    {
        return 'mms_communications';
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Communication');
    }

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
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBulkMailCampaigns::route('/'),
            'create' => CreateBulkMailCampaign::route('/create'),
            'view' => ViewBulkMailCampaign::route('/{record}'),
            'edit' => EditBulkMailCampaign::route('/{record}/edit'),
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
