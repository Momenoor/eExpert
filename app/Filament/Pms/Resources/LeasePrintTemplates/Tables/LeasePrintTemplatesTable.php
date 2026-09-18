<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeasePrintTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('pages'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contract_format')
                    ->label(__('Contract Format'))
                    ->badge(),
                TextColumn::make('pages_count')
                    ->label(__('Pages')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('No print templates yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
