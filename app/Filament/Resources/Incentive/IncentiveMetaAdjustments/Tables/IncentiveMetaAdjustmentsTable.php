<?php

namespace App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncentiveMetaAdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('field_name')
                    ->label(__('Field Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('field_value')
                    ->label(__('Field Value'))
                    ->placeholder(__('Any value'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('percentage_adjustment')
                    ->label(__('Adjustment %'))
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
