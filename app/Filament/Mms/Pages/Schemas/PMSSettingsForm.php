<?php

namespace App\Filament\Mms\Pages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Office-policy figures the PMS module calculates from — mirrors
 * `PayrollSettingsForm`: every field here overrides a `DEFAULT_*` used
 * in-code via `Setting::get()`, never a new figure the code doesn't already
 * know how to fall back to. The RERA rent-increase bands and the 90-day
 * notice window are deliberately absent — those are statutory figures set by
 * law, not office policy, so there is nothing here to configure for them.
 */
class PMSSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Tax'))
                ->description(__('VAT applied where a unit\'s own classification (residential/commercial) doesn\'t settle it outright.'))
                ->icon(Heroicon::Calculator)
                ->columns(2)
                ->schema([
                    TextInput::make('pms_mixed_use_vat_rate')
                        ->label(__('Mixed-Use VAT Rate'))
                        ->helperText(__('As a fraction, e.g. 0.05 for 5%. Applied to a unit classified Mixed Use.'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.0001)
                        ->required()
                        ->default(0.05),
                ]),

            Section::make(__('Quotations & Instalments'))
                ->icon(Heroicon::Banknotes)
                ->columns(2)
                ->schema([
                    TextInput::make('pms_attestation_fee_estimate')
                        ->label(__('Attestation Fee Estimate (AED)'))
                        ->helperText(__('Itemised on a quotation as an estimate — the real government fee is confirmed at contract stage.'))
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->default(0),
                    TextInput::make('pms_bounced_cheque_penalty')
                        ->label(__('Bounced Cheque Penalty (AED)'))
                        ->helperText(__('Added to the balance due whenever an instalment is marked bounced.'))
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->default(100),
                ]),
        ]);
    }
}
