<?php

namespace App\Filament\Pms\Resources\Properties\Tables;

use App\Models\Property;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('units')->with('owners.ownerProfile.ownerGroup'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Property Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('emirate')
                    ->label(__('Emirate'))
                    ->badge(),
                TextColumn::make('units_count')
                    ->label(__('Units')),
                TextColumn::make('landlord')
                    ->label(__('Landlord'))
                    // The name a contract would show — a shared group's
                    // collective name once every owner belongs to one,
                    // otherwise each owner's own name.
                    ->state(fn (Property $record): string => $record->landlordName())
                    ->placeholder('—'),
                TextColumn::make('year_built')
                    ->label(__('Year Built'))
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Property $record, DeleteAction $action): void {
                        if ($record->hasLeaseHistory()) {
                            Notification::make()
                                ->danger()
                                ->title(__('Could not continue'))
                                ->body(__('This property has units linked to a lease and cannot be deleted.'))
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->emptyStateHeading(__('No properties yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
