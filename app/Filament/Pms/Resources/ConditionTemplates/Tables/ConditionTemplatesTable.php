<?php

namespace App\Filament\Pms\Resources\ConditionTemplates\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConditionTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('emirate')
                    ->label(__('Emirate'))
                    ->badge(),
                TextColumn::make('contract_format')
                    ->label(__('Contract Format'))
                    ->badge(),
                TextColumn::make('items_count')
                    ->label(__('Special Conditions')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No conditions templates yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
