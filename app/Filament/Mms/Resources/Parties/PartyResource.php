<?php

namespace App\Filament\Mms\Resources\Parties;

use App\Filament\Mms\Resources\Parties\Pages\CreateParty;
use App\Filament\Mms\Resources\Parties\Pages\EditParty;
use App\Filament\Mms\Resources\Parties\Pages\ListParties;
use App\Filament\Mms\Resources\Parties\Pages\ViewParty;
use App\Filament\Mms\Resources\Parties\RelationManagers\MattersRelationManager;
use App\Filament\Mms\Resources\Parties\Schemas\PartyForm;
use App\Filament\Mms\Resources\Parties\Schemas\PartyInfolist;
use App\Filament\Mms\Resources\Parties\Tables\PartiesTable;
use App\Models\Party;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasRole('expert')) {
            $user = auth()->user();
            $query->where(function ($q) use ($user) {
                // Own party record
                $q->where('user_id', $user->id)
                    // Or parties involved in matters the expert is involved in
                    ->orWhereHas('matters', function ($mq) use ($user) {
                        $mq->whereHas('parties', function ($pq) use ($user) {
                            $pq->where('parties.user_id', $user->id);
                        });
                    });
            });
        }

        return $query;
    }

    public static function getModelLabel(): string
    {
        return __('Party');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Parties');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PartyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PartyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MattersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParties::route('/'),
            'create' => CreateParty::route('/create'),
            'view' => ViewParty::route('/{record}'),
            'edit' => EditParty::route('/{record}/edit'),
        ];
    }
}
