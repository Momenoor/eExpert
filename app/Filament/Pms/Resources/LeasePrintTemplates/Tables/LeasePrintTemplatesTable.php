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
            ->modifyQueryUsing(fn ($query) => $query->withCount('pages')->with('ownerGroup'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_type')
                    ->label(__('Document Type'))
                    ->badge(),
                TextColumn::make('contract_format')
                    ->label(__('Contract Format'))
                    ->badge()
                    // A group-scoped template's contract_format is a
                    // synthetic slug, not something meant to be shown.
                    ->formatStateUsing(fn (?string $state): ?string => str_starts_with((string) $state, 'owner_group_') ? null : $state)
                    ->placeholder('—'),
                TextColumn::make('ownerGroup.name')
                    ->label(__('Owner Group'))
                    ->placeholder('—'),
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
