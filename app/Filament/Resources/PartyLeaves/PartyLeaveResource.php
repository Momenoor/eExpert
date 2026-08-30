<?php

namespace App\Filament\Resources\PartyLeaves;

use App\Filament\Resources\PartyLeaves\Pages\CreatePartyLeave;
use App\Filament\Resources\PartyLeaves\Pages\EditPartyLeave;
use App\Filament\Resources\PartyLeaves\Pages\ListPartyLeaves;
use App\Filament\Resources\PartyLeaves\Schemas\PartyLeaveForm;
use App\Filament\Resources\PartyLeaves\Tables\PartyLeavesTable;
use App\Models\PartyLeave;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PartyLeaveResource extends Resource
{
    protected static ?string $model = PartyLeave::class;

    public static function getModelLabel(): string
    {
        return __('Leave / Vacation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Leaves / Vacations');
    }

    public static function getNavigationLabel(): string
    {
        return __('Leaves / Vacations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Finance');
    }

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reason';

    public static function form(Schema $schema): Schema
    {
        return PartyLeaveForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartyLeavesTable::configure($table);
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
            'index' => ListPartyLeaves::route('/'),
            'create' => CreatePartyLeave::route('/create'),
            'edit' => EditPartyLeave::route('/{record}/edit'),
        ];
    }
}
