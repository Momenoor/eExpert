<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Models\Quotation;
use App\Services\QuotationService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuotationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Quotation'))
                    ->schema([
                        TextEntry::make('party.name')
                            ->label(__('Prospect / Tenant')),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('validity_date')
                            ->label(__('Valid Until'))
                            ->date(),
                        TextEntry::make('security_deposit')
                            ->label(__('Security Deposit'))
                            ->numeric(decimalPlaces: 2),
                    ])->columns(4),

                Section::make(__('Units'))
                    ->schema([
                        RepeatableEntry::make('units')
                            ->label('')
                            ->schema([
                                TextEntry::make('unit_number')
                                    ->label(__('Unit')),
                                TextEntry::make('pivot.offered_rent')
                                    ->label(__('Offered Rent'))
                                    ->numeric(decimalPlaces: 2),
                                TextEntry::make('pivot.vat_amount')
                                    ->label(__('VAT'))
                                    ->numeric(decimalPlaces: 2),
                            ])
                            ->columns(3),
                    ]),

                Section::make(__('Totals'))
                    ->schema([
                        TextEntry::make('base_rent')
                            ->label(__('Base Rent'))
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('vat_amount')
                            ->label(__('VAT'))
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('attestation_fee_estimate')
                            ->label(__('Attestation Fee (Estimate)'))
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('total_amount')
                            ->label(__('Total Due'))
                            ->numeric(decimalPlaces: 2)
                            ->weight('bold'),
                    ])->columns(4),

                Section::make(__('Payment Schedule'))
                    ->description(__('Informational only — a Contract is what actually creates instalments.'))
                    ->schema([
                        TextEntry::make('payment_schedule')
                            ->label('')
                            ->state(fn (Quotation $record): string => collect(app(QuotationService::class)->paymentSchedule($record))
                                ->map(fn (float $amount, int $index): string => __(':nth: :amount AED', [
                                    'nth' => $index + 1,
                                    'amount' => number_format($amount, 2),
                                ]))
                                ->implode(' · ')),
                    ]),
            ]);
    }
}
