<?php

namespace App\Filament\Resources\Incentive\IncentiveExtraRules\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncentiveExtraRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('min_count')->label(__('Min Matters'))->sortable(),
                TextColumn::make('max_count')->label(__('Max Matters'))->placeholder(__('No limit')),
                TextColumn::make('extra_percentage')->label(__('Extra %'))->suffix('%')->sortable(),
            ])
            ->defaultSort('min_count')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([

            ])
            ->emptyStateHeading(__('No extra rules yet'))
            ->emptyStateDescription(__('Add rules per the PDF: 5 matters = 1.5%, 6 = 2%, >6 = 3%'))
            ->emptyStateActions([
                CreateAction::make()->label(__('Add Rule')),
            ]);
    }
}
