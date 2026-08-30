<?php

namespace App\Filament\Resources\MatterRequests;

use App\Enums\RequestStatus;
use App\Filament\Resources\MatterRequests\Pages\CreateMatterRequest;
use App\Filament\Resources\MatterRequests\Pages\EditRequest;
use App\Filament\Resources\MatterRequests\Pages\ListMatterRequests;
use App\Filament\Resources\MatterRequests\Pages\ViewMatterRequest;
use App\Filament\Resources\MatterRequests\Schemas\MatterRequestForm;
use App\Filament\Resources\MatterRequests\Schemas\MatterRequestInfolist;
use App\Filament\Resources\MatterRequests\Tables\MatterRequestsTable;
use App\Models\MatterRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MatterRequestResource extends Resource
{
    protected static ?string $model = MatterRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Newspaper;

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::count();
    }

    public static function getPluralModelLabel(): string
    {
        return __('Requests');
    }

    public static function getModelLabel(): string
    {
        return __('Requests');
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        // The $record is passed in automatically by Filament
        return $record?->type?->getLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return MatterRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MatterRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MatterRequestsTable::configure($table);
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
            'index' => ListMatterRequests::route('/'),
            // 'create' => CreateMatterRequest::route('/create'),
            'view' => ViewMatterRequest::route('/{record}'),
            // 'edit' => EditRequest::route('/{record}/edit'),
        ];
    }

    private static function count(): int
    {
        return static::getEloquentQuery()->where('status', RequestStatus::PENDING->value)->count();
    }
}
