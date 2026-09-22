<?php

namespace App\Filament\Pms\Resources\OwnerProfiles\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OwnerProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('party'))
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Owner'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_primary')
                    ->label(__('Primary'))
                    ->boolean(),
                TextColumn::make('identification_number')
                    ->label(__('Identification Number'))
                    ->placeholder('—'),
                TextColumn::make('trn')
                    ->label(__('TRN'))
                    ->placeholder('—'),
                TextColumn::make('bank_name')
                    ->label(__('Bank Name'))
                    ->placeholder('—'),
                TextColumn::make('iban')
                    ->label(__('IBAN'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('party.name')
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading(__('No owners yet'))
            ->emptyStateActions([
                CreateAction::make()->label(__('Add Owner')),
            ]);
    }
}
