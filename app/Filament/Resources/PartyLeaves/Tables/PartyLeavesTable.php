<?php

namespace App\Filament\Resources\PartyLeaves\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartyLeavesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.name')->label(__('Assistant / Expert'))->searchable()->sortable(),
                TextColumn::make('start_date')->label(__('Start Date'))->date()->sortable(),
                TextColumn::make('end_date')->label(__('End Date'))->date()->sortable(),
                TextColumn::make('reason')->label(__('Reason'))->limit(40)->placeholder('—'),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([

            ])
            ->emptyStateHeading(__('No leaves recorded yet'))
            ->emptyStateActions([
                CreateAction::make()->label(__('Add Leave')),
            ]);
    }
}
