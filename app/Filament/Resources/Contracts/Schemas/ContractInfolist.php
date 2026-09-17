<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Contract;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Contract'))
                    ->schema([
                        TextEntry::make('landlord')
                            ->label(__('Landlord'))
                            ->state(fn (Contract $record): string => $record->landlordName() ?: '—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('start_date')
                            ->label(__('Start Date'))
                            ->date(),
                        TextEntry::make('end_date')
                            ->label(__('End Date'))
                            ->date(),
                        TextEntry::make('grace_period_days')
                            ->label(__('Grace Period (Days)')),
                        TextEntry::make('total_base_rent')
                            ->label(__('Total Base Rent'))
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('security_deposit_amount')
                            ->label(__('Security Deposit'))
                            ->numeric(decimalPlaces: 2),
                    ])->columns(4),

                Section::make(__('Attestation'))
                    ->schema([
                        TextEntry::make('attestation_system')
                            ->label(__('Attestation System'))
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('attestation_serial_number')
                            ->label(__('Attestation Serial Number'))
                            ->placeholder('—'),
                        TextEntry::make('title_deed_number')
                            ->label(__('Title Deed Number'))
                            ->placeholder('—'),
                        TextEntry::make('attestation_fee_payer')
                            ->label(__('Attestation Fee Payer'))
                            ->badge(),
                        TextEntry::make('attestation_status')
                            ->label(__('Attestation Status'))
                            ->badge(),
                        TextEntry::make('dispute_status')
                            ->label(__('Dispute Status'))
                            ->badge(),
                    ])->columns(3),

                Section::make(__('Tenants'))
                    ->schema([
                        RepeatableEntry::make('contractParties')
                            ->label('')
                            ->schema([
                                TextEntry::make('party.name')
                                    ->label(__('Party')),
                                TextEntry::make('role')
                                    ->label(__('Role'))
                                    ->badge(),
                            ])
                            ->columns(2),
                    ]),

                Section::make(__('Units'))
                    ->schema([
                        RepeatableEntry::make('units')
                            ->label('')
                            ->schema([
                                TextEntry::make('unit_number')
                                    ->label(__('Unit')),
                                TextEntry::make('building.name')
                                    ->label(__('Building')),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
