<?php

namespace App\Filament\Resources\Matters;

use App\Filament\Resources\Matters\Pages\CreateMatter;
use App\Filament\Resources\Matters\Pages\EditMatter;
use App\Filament\Resources\Matters\Pages\ListMatters;
use App\Filament\Resources\Matters\Pages\ViewMatter;
use App\Filament\Resources\Matters\Schemas\MatterForm;
use App\Filament\Resources\Matters\Schemas\MatterInfolist;
use App\Filament\Resources\Matters\Tables\MattersTable;
use App\Models\Matter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MatterResource extends Resource
{
    protected static ?string $model = Matter::class;

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user->can('ViewAny:Matter') || $user->can('ViewOwn:Matter');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view', $record);
    }

    public static function getModelLabel(): string
    {
        return __('Matter');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Matters');
    }

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return MatterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MatterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MattersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatters::route('/'),
            'create' => CreateMatter::route('/create'),
            'view' => ViewMatter::route('/{record}'),
            'edit' => EditMatter::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withTrashed() // Ensure trashed records are visible
            // SoftDeletingScope left intact — tabs use onlyTrashed() to control visibility
            ->with([
                'mainPartiesOnly.party',
                'mainPartiesOnly.representatives.party',
                'expertsOnly.party',
                'fees.allocations',
                'court',
                'type',
                'matterParties',
            ])
            ->orderByRaw('COALESCE(parent_id, id) ASC, id ASC');

        $user = auth()->user();

        // Fail CLOSED. canViewAny() admits anyone holding ViewOwn:Matter, so a
        // user restricted to their own matters whose Party link is missing must
        // see nothing — previously the scoping clause additionally required
        // $user->party, and without it the query was left completely unscoped,
        // exposing every matter in the office.
        if (! $user->can('ViewAny:Matter') && $user->can('ViewOwn:Matter')) {
            if (! $user->party) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereHas('matterParties', fn (Builder $q) => $q->where('party_id', $user->party->id));
        }

        return $query;
    }
}
