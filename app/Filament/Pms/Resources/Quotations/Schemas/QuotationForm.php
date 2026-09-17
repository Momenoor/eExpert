<?php

namespace App\Filament\Pms\Resources\Quotations\Schemas;

use App\Models\Party;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Input only — the priced figures (base rent, VAT, total) are never entered
 * here. `QuotationService::generate()` computes them from each line's
 * offered rent and the unit's own `vatRate()`, so there is nowhere on this
 * form for those numbers to drift out of sync with what the units actually
 * carry.
 */
class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Quotation'))
                    ->schema([
                        Select::make('party_id')
                            ->label(__('Prospect / Tenant'))
                            ->options(fn (): array => Party::withRole('tenant')->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        DatePicker::make('validity_date')
                            ->label(__('Valid Until'))
                            ->default(now()->addDays(14))
                            ->required(),
                        TextInput::make('security_deposit')
                            ->label(__('Security Deposit (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0),
                        TextInput::make('number_of_installments')
                            ->label(__('Number of Instalments'))
                            ->helperText(__('How many cheques/payments the tenant would split the total across.'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(1)
                            ->required(),
                    ])->columns(2),

                Section::make(__('Units'))
                    ->schema([
                        Repeater::make('units')
                            ->label(__('Units Offered'))
                            ->schema([
                                Select::make('unit_id')
                                    ->label(__('Unit'))
                                    ->options(fn (): array => Unit::query()
                                        ->with('property')
                                        ->get()
                                        ->mapWithKeys(fn (Unit $unit): array => [
                                            $unit->id => "{$unit->property?->name} — {$unit->unit_number}",
                                        ])->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct(),
                                TextInput::make('offered_rent')
                                    ->label(__('Offered Rent (AED/year)'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->addActionLabel(__('Add Unit'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
