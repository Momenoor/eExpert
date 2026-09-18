<?php

namespace App\Filament\Pms\Resources\Leases\Schemas;

use App\Enums\PMS\ContractType;
use App\Enums\PMS\DesignatedUse;
use App\Enums\PMS\Emirate;
use App\Enums\PMS\InstallmentPaymentMethod;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\PropertyClassification;
use App\Models\ConditionTemplate;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Property;
use App\Models\Unit;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * The guided, step-by-step lease creation flow — used only by `CreateLease`
 * (via Filament's `HasWizard` page concern). Editing an existing lease
 * keeps the flat `LeaseForm` (a correction, not a fresh walkthrough). A
 * lease made here always covers exactly one property.
 */
class LeaseWizardForm
{
    /**
     * @return list<Step>
     */
    public static function steps(): array
    {
        return [
            self::propertyStep(),
            self::unitsStep(),
            self::tenantsStep(),
            self::contractDetailsStep(),
            self::installmentsStep(),
            self::conditionsStep(),
        ];
    }

    private static function propertyStep(): Step
    {
        return Step::make(__('Property'))
            ->schema([
                ToggleButtons::make('property_id')
                    ->label(__('Select the Property'))
                    ->options(fn (): array => Property::orderBy('name')->pluck('name', 'id')->all())
                    ->icons(fn (): array => Property::orderBy('name')->pluck('id')
                        ->mapWithKeys(fn (int $id): array => [$id => Heroicon::OutlinedBuildingOffice2])
                        ->all())
                    ->required()
                    ->live()
                    ->columns(5)
                    ->extraAttributes(['class' => 'pms-property-picker'])
                    ->afterStateUpdated(function (Set $set, Get $get, ?int $state): void {

                        $set('condition_template_id', null);

                        if ($state !== null && blank($get('government_contract_number'))) {
                            $set('government_contract_number', self::suggestContractNumber($state));
                        }
                    }),
            ]);
    }

    /**
     * Its own step, scoped to whichever property was picked on the step
     * before — defaults to that property's first unit already selected
     * rather than an empty required row, since a lease can't mean anything
     * yet without knowing what it covers.
     */
    private static function unitsStep(): Step
    {
        return Step::make(__('Units'))
            ->schema([
                Repeater::make('units')
                    ->label(__('Units'))
                    ->schema([
                        Select::make('unit_id')
                            ->label(__('Unit'))
                            ->options(function (Get $get): array {
                                $propertyId = $get('../../property_id');

                                if (blank($propertyId)) {
                                    return [];
                                }

                                return Unit::query()
                                    ->where('property_id', $propertyId)
                                    ->pluck('unit_number', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->distinct()
                            ->live(),
                    ])
                    ->table([
                        TableColumn::make(__('Unit')),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel(__('Add Unit'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?array $state): void {
                        $set('condition_template_id', self::suggestConditionTemplateId(
                            $get('property_id'),
                            self::selectedUnitIds($state),
                        ));
                    })
                    ->columnSpanFull(),
            ]);
    }

    private static function tenantsStep(): Step
    {
        return Step::make(__('Tenants'))
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
                    ->table([
                        TableColumn::make(__('Party')),
                        TableColumn::make(__('Role')),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel(__('Add Tenant'))
                    ->columnSpanFull(),
            ]);
    }

    private static function contractDetailsStep(): Step
    {
        return Step::make(__('Contract Details'))
            ->schema([
                Section::make(__('Dates'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                'end_date',
                                $state ? Carbon::parse($state)->addYear()->toDateString() : null,
                            )),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->required()
                            ->afterOrEqual('start_date')
                            ->helperText(__('Defaults to one year after the start date — adjust if the contract runs differently.')),
                        DatePicker::make('issue_date')
                            ->label(__('Issue Date'))
                            ->default(now()),
                        TextInput::make('grace_period_days')
                            ->label(__('Grace Period (Days)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ])->columns(3),

                Section::make(__('Financials'))
                    ->schema([
                        TextInput::make('total_base_rent')
                            ->label(__('Total Base Rent (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('annual_rent')
                            ->label(__('Annual Rent (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('multiple_rent_amount')
                            ->label(__('Multiple Rent Amount (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('security_deposit_amount')
                            ->label(__('Security Deposit (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0)
                            ->live(onBlur: true),
                    ])->columns(3),

                Section::make(__('Contract Identification'))
                    ->schema([
                        TextInput::make('government_contract_number')
                            ->label(__('Contract No.'))
                            ->helperText(__('Auto-suggested from the property — change it if needed.'))
                            ->maxLength(255),
                        Select::make('contract_type')
                            ->label(__('Contract Type'))
                            ->options(ContractType::class),
                    ])->columns(2),

                Section::make(__('Occupancy & Use'))
                    ->schema([
                        Select::make('designated_use')
                            ->label(__('Designated Use'))
                            ->helperText(__('Commercial/industrial contracts only.'))
                            ->options(DesignatedUse::class),
                        TextInput::make('number_of_occupants')
                            ->label(__('No. of Occupants'))
                            ->helperText(__('Residential contracts only.'))
                            ->numeric()
                            ->minValue(0),
                        Checkbox::make('allow_multiple_licenses')
                            ->label(__('Allow Multiple Licenses'))
                            ->helperText(__('Commercial/industrial contracts only.')),
                    ])->columns(3),

                Section::make(__('Power of Attorney'))
                    ->description(__('Only needed when a delegated representative signs on behalf of a party.'))
                    ->schema([
                        TextInput::make('poa_authority_number')
                            ->label(__('POA Authority No.'))
                            ->maxLength(255),
                        TextInput::make('poa_identification_number')
                            ->label(__('POA EID No. / Trade License No.'))
                            ->maxLength(255),
                        TextInput::make('poa_unified_number')
                            ->label(__('POA Unified No.'))
                            ->maxLength(255),
                        TextInput::make('poa_name')
                            ->label(__('POA Name'))
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    private static function installmentsStep(): Step
    {
        return Step::make(__('Installments & Payments'))
            ->schema([
                Placeholder::make('installments_target')
                    ->label(__('Required Total'))
                    ->content(function (Get $get): string {
                        $target = self::requiredInstallmentsTotal($get);

                        return __(':amount AED (rent + VAT if applicable + security deposit)', [
                            'amount' => number_format($target, 2),
                        ]);
                    }),
                Repeater::make('installments')
                    ->label(__('Installments'))
                    ->schema([
                        Select::make('payment_method')
                            ->label(__('Payment Method'))
                            ->options(InstallmentPaymentMethod::class)
                            ->required()
                            ->live(),
                        DatePicker::make('payment_date')
                            ->label(__('Payment Date'))
                            ->required()
                            ->live(onBlur: true)
                            ->helperText(function (Get $get): ?string {
                                return self::installmentDateWarning($get, $get('payment_date'));
                            }),
                        TextInput::make('amount')
                            ->label(__('Amount (AED)'))
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('reference_number')
                            ->label(fn (Get $get): string => match ($get('payment_method')) {
                                InstallmentPaymentMethod::POST_DATED_CHEQUE->value => __('Check Number'),
                                InstallmentPaymentMethod::BANK_TRANSFER->value, InstallmentPaymentMethod::DIRECT_DEBIT_UAEDD->value => __('Transfer Number'),
                                default => __('Reference Number'),
                            })
                            ->visible(fn (Get $get): bool => ! in_array($get('payment_method'), [
                                InstallmentPaymentMethod::CASH->value,
                                InstallmentPaymentMethod::CREDIT_CARD->value,
                            ], true))
                            ->required(fn (Get $get): bool => in_array($get('payment_method'), [
                                InstallmentPaymentMethod::POST_DATED_CHEQUE->value,
                                InstallmentPaymentMethod::BANK_TRANSFER->value,
                                InstallmentPaymentMethod::DIRECT_DEBIT_UAEDD->value,
                            ], true))
                            ->maxLength(255),
                        Checkbox::make('is_security_deposit')
                            ->label(__('Security Deposit'))
                            ->helperText(__('Excluded from VAT.')),
                    ])
                    ->table([
                        TableColumn::make(__('Payment Method')),
                        TableColumn::make(__('Payment Date')),
                        TableColumn::make(__('Amount')),
                        TableColumn::make(__('Reference')),
                        TableColumn::make(__('Security Deposit')),
                    ])
                    ->columns(5)
                    ->minItems(1)
                    ->addActionLabel(__('Add Installment'))
                    ->columnSpanFull()
                    ->rule(function (Get $get): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $target = self::requiredInstallmentsTotal($get);
                            $sum = round(collect($value)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);

                            if (abs($sum - $target) > 0.01) {
                                $fail(__('Instalment amounts must sum to :target AED (rent + VAT if applicable + security deposit) — currently :sum AED.', [
                                    'target' => number_format($target, 2),
                                    'sum' => number_format($sum, 2),
                                ]));
                            }
                        };
                    }),
            ]);
    }

    private static function conditionsStep(): Step
    {
        return Step::make(__('Special Conditions'))
            ->schema([
                Select::make('condition_template_id')
                    ->label(__('Conditions Template'))
                    ->options(fn (): array => ConditionTemplate::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->helperText(__('Auto-suggested from the selected property and units — change it if a different template applies.')),
            ]);
    }

    /**
     * @param  list<int>|null  $unitIds
     */
    private static function suggestConditionTemplateId(?int $propertyId, ?array $unitIds): ?int
    {
        $property = $propertyId ? Property::find($propertyId) : null;
        $unit = ! empty($unitIds) ? Unit::find($unitIds[0]) : null;

        if ($property === null || $property->emirate === null) {
            return null;
        }

        $format = match (true) {
            $property->emirate === Emirate::DUBAI => 'dubai_ejari',
            $unit !== null && in_array($unit->property_classification, [PropertyClassification::COMMERCIAL, PropertyClassification::INDUSTRIAL], true) => 'sharjah_commercial',
            default => 'sharjah_residential',
        };

        return ConditionTemplate::where('contract_format', $format)->value('id');
    }

    /**
     * A serial unique to the property: its own `property_number`, followed
     * by how many leases have already covered a unit in it — still just a
     * suggestion, the office can type over it.
     */
    private static function suggestContractNumber(int $propertyId): ?string
    {
        $property = Property::find($propertyId);

        if ($property === null || blank($property->property_number)) {
            return null;
        }

        $sequence = Lease::whereHas('units', fn ($query) => $query->where('property_id', $propertyId))->count() + 1;

        return sprintf('%s-%04d', $property->property_number, $sequence);
    }

    /**
     * A non-blocking heads-up when a row's payment date falls outside the
     * lease's own start/end — legitimate in some cases (a deposit paid
     * ahead of the start date), so it warns rather than refuses to save.
     */
    private static function installmentDateWarning(Get $get, ?string $paymentDate): ?string
    {
        // `$get` here is scoped to the current row inside the `installments`
        // repeater — the lease's own dates live two levels up, outside it.
        $startDate = $get('../../start_date');
        $endDate = $get('../../end_date');

        if (blank($paymentDate) || blank($startDate) || blank($endDate)) {
            return null;
        }

        $date = Carbon::parse($paymentDate);

        if ($date->between(Carbon::parse($startDate), Carbon::parse($endDate))) {
            return null;
        }

        return __('This date falls outside the lease period (:start – :end).', [
            'start' => Carbon::parse($startDate)->format('d/m/Y'),
            'end' => Carbon::parse($endDate)->format('d/m/Y'),
        ]);
    }

    /**
     * @param  array<int, array{unit_id?: int|null}>|null  $unitRows
     * @return list<int>
     */
    private static function selectedUnitIds(?array $unitRows): array
    {
        return collect($unitRows ?? [])->pluck('unit_id')->filter()->values()->all();
    }

    /**
     * The rental-rate-weighted VAT rate across the units picked so far —
     * the same formula `Lease::vatRate()` uses, computed here before the
     * lease itself exists yet.
     */
    private static function selectedUnitsVatRate(Get $get): float
    {
        $units = Unit::query()->whereIn('id', self::selectedUnitIds($get('units')))->get();
        $totalRentalRate = (float) $units->sum(fn (Unit $unit): float => (float) $unit->rental_rate);

        if ($totalRentalRate <= 0.0) {
            return $units->isEmpty() ? 0.0 : (float) $units->avg(fn (Unit $unit): float => $unit->vatRate());
        }

        $weighted = $units->sum(fn (Unit $unit): float => (float) $unit->rental_rate * $unit->vatRate());

        return $weighted / $totalRentalRate;
    }

    private static function requiredInstallmentsTotal(Get $get): float
    {
        $totalRent = (float) ($get('total_base_rent') ?? 0);
        $securityDeposit = (float) ($get('security_deposit_amount') ?? 0);
        $vatRate = self::selectedUnitsVatRate($get);

        return round($totalRent + ($totalRent * $vatRate) + $securityDeposit, 2);
    }
}
