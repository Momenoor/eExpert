<?php

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Filament\Resources\Matters\MatterResource;
use App\Filament\Resources\Matters\Tables\MattersTable;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class MattersRelationManager extends RelationManager
{
    protected static string $relationship = 'matters';

    protected static ?string $relatedResource = MatterResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Matters');
    }

    public function table(Table $table): Table
    {
        return MattersTable::configure($table)
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('No matters found for this party'));
    }

    //    public function getTableQuery(): Builder|Relation|null
    //    {
    //        return parent::getTableQuery()->where('party_id', $this->ownerRecord->id);
    //    }
}
