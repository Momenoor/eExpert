<?php

namespace App\Filament\Resources\Buildings\Tables;

use App\Models\Building;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('units')->with('owners.ownerProfile.ownerGroup'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Building Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label(__('City / Emirate'))
                    ->searchable(),
                TextColumn::make('units_count')
                    ->label(__('Units')),
                TextColumn::make('landlord')
                    ->label(__('Landlord'))
                    // The name a contract would show — a shared group's
                    // collective name once every owner belongs to one,
                    // otherwise each owner's own name.
                    ->state(fn (Building $record): string => $record->landlordName())
                    ->placeholder('—'),
                TextColumn::make('year_built')
                    ->label(__('Year Built'))
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No buildings yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
