<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IncentiveCalculationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable()
                    ->description(fn ($record) => $record->notes ? \Str::limit($record->notes, 50) : null),
                TextColumn::make('period_start')->label(__('Period Start'))->date()->sortable(),
                TextColumn::make('period_end')->label(__('Period End'))->date()->sortable(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'warning',
                        'finalized' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => __('Draft'),
                        'finalized' => __('Finalized'),
                        default => $state,
                    }),
                TextColumn::make('lines_count')->counts('lines')->label(__('Fees')),
                TextColumn::make('finalized_at')->label(__('Finalized'))->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('createdBy.name')->label(__('Created By')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(['draft' => __('Draft'), 'finalized' => __('Finalized')]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([

            ]);
    }
}
