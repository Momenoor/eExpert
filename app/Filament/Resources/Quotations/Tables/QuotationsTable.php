<?php

namespace App\Filament\Resources\Quotations\Tables;

use App\Enums\PMS\QuotationStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('party'))
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Prospect / Tenant'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_rent')
                    ->label(__('Base Rent'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('vat_amount')
                    ->label(__('VAT'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold'),
                TextColumn::make('validity_date')
                    ->label(__('Valid Until'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(QuotationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('No quotations yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
