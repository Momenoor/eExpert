<?php

namespace App\Filament\Pms\Resources\Leases\Schemas;

use App\Enums\PMS\LeasePartyRole;
use App\Models\Party;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Raw-input lease creation — the other path, converting an accepted
 * `Quotation`, goes through `LeaseService::createFromQuotation()` from
 * the quotation's own view page instead of this form.
 */
class LeaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Lease'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->required(),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->required()
                            ->afterOrEqual('start_date'),
                        TextInput::make('grace_period_days')
                            ->label(__('Grace Period (Days)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('total_base_rent')
                            ->label(__('Total Base Rent (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                        TextInput::make('security_deposit_amount')
                            ->label(__('Security Deposit (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0),
                    ])->columns(3),

                Section::make(__('Tenants'))
                    ->description(__('At least one Primary Tenant is required; co-tenants and guarantors are optional.'))
                    ->schema([
                        Repeater::make('tenants')
                            ->label(__('Tenants'))
                            ->schema([
                                Select::make('party_id')
                                    ->label(__('Party'))
                                    ->options(fn (): array => Party::withRole('tenant')->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct(),
                                Select::make('role')
                                    ->label(__('Role'))
                                    ->options(LeasePartyRole::class)
                                    ->default(LeasePartyRole::PRIMARY_TENANT->value)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel(__('Add Tenant'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Units'))
                    ->schema([
                        Select::make('units')
                            ->label(__('Units'))
                            ->options(fn (): array => Unit::query()
                                ->with('property')
                                ->get()
                                ->mapWithKeys(fn (Unit $unit): array => [
                                    $unit->id => "{$unit->property?->name} — {$unit->unit_number}",
                                ])->all())
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
