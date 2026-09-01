<?php

namespace App\Filament\Resources\MatterRequests\Tables;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Filament\Actions\Request\ApproveRequestAction;
use App\Filament\Actions\Request\RejectRequestAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MatterRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matter.number')
                    ->formatStateUsing(fn ($record) => $record->matter->number.'/'.$record->matter->year)
                    ->url(fn ($record) => route('filament.admin.resources.matters.view', $record->matter_id))
                    ->label(__('Matter')),
                TextColumn::make('requestBy.display_name')
                    ->label(__('Request By'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('Status'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('comment')
                    ->label(__('Comment'))
                    ->markdown()
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('approvedBy.display_name')
                    ->numeric()
                    ->label(__('Handled By'))
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->label(__('Handled At'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('approved_comment')
                    ->label(__('Handling Note'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RequestStatus::class)
                    ->multiple()
                    ->label(__('Status'))
                    ->default([RequestStatus::PENDING, RequestStatus::DISPUTED]),
                SelectFilter::make('type')
                    ->options(RequestType::class)
                    ->multiple()
                    ->label(__('Type')),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ApproveRequestAction::make(),
                RejectRequestAction::make(),
                DeleteAction::make()->visible(fn ($record) => $record->status === RequestStatus::PENDING && auth()->user()->can('Delete:MatterRequest')),
            ])
            ->toolbarActions([

            ]);
    }
}
