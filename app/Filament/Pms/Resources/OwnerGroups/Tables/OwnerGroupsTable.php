<?php

namespace App\Filament\Pms\Resources\OwnerGroups\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OwnerGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('ownerProfiles'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Group Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner_profiles_count')
                    ->label(__('Members')),
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
            ->emptyStateHeading(__('No owner groups yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
