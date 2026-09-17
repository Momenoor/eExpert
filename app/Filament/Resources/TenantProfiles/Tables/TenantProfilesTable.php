<?php

namespace App\Filament\Resources\TenantProfiles\Tables;

use App\Enums\PMS\TenantType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TenantProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('party'))
            ->columns([
                TextColumn::make('party.name')
                    ->label(__('Tenant'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant_type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('identification_type')
                    ->label(__('Identification Type'))
                    ->badge(),
                TextColumn::make('identification_number')
                    ->label(__('Identification Number'))
                    ->searchable(),
                TextColumn::make('trn')
                    ->label(__('TRN'))
                    ->placeholder('—'),
                TextColumn::make('emergency_contact_name')
                    ->label(__('Emergency Contact'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('party.name')
            ->filters([
                SelectFilter::make('tenant_type')
                    ->label(__('Type'))
                    ->options(TenantType::class),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading(__('No tenants yet'))
            ->emptyStateActions([
                CreateAction::make()->label(__('Add Tenant')),
            ]);
    }
}
