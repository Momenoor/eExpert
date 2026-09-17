<?php

namespace App\Filament\Resources\Contracts\Tables;

use App\Enums\PMS\AttestationStatus;
use App\Enums\PMS\ContractStatus;
use App\Models\Contract;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['contractParties.party', 'units.building']))
            ->columns([
                TextColumn::make('primary_tenant')
                    ->label(__('Primary Tenant'))
                    ->state(fn (Contract $record): string => $record->primaryTenant()?->party?->name ?? '—'),
                TextColumn::make('start_date')
                    ->label(__('Start Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('End Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('total_base_rent')
                    ->label(__('Total Base Rent'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('attestation_status')
                    ->label(__('Attestation'))
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ContractStatus::class),
                SelectFilter::make('attestation_status')
                    ->label(__('Attestation Status'))
                    ->options(AttestationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading(__('No contracts yet'))
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
