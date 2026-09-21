<?php

namespace App\Filament\Pms\Resources\Leases\Schemas;

use App\Enums\PMS\Emirate;
use App\Enums\PMS\InstallmentPaymentMethod;
use App\Enums\PMS\LeasePartyRole;
use App\Enums\PMS\PropertyClassification;
use App\Enums\PMS\YesNo;
use App\Models\ConditionTemplate;
use App\Models\Lease;
use App\Models\Party;
use App\Models\Unit;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Raw-input lease creation — the other path, converting an accepted
 * `Quotation`, goes through `LeaseService::createFromQuotation()` from
 * the quotation's own view page instead of this form. Also reused,
 * unchanged, by `EditLease` — only reachable while the lease is `DRAFT`.
 */
class LeaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Dates'))
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Start Date'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $end = $state ? Lease::fullYearEnd($state)->toDateString() : null;

                                $set('end_date', $end);
                                self::syncAnnualRent($set, $state, $end, $get('total_base_rent'));
                            }),
                        DatePicker::make('end_date')
                            ->label(__('End Date'))
                            ->required()
                            ->afterOrEqual('start_date')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => self::syncAnnualRent($set, $get('start_date'), $state, $get('total_base_rent')))
                            ->helperText(__('Defaults to a full year — the day before the start date\'s anniversary. Adjust if the contract runs differently.')),
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
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get, $state) => self::syncAnnualRent($set, $get('start_date'), $get('end_date'), $state)),
                        TextInput::make('annual_rent')
                            ->label(__('Annual Rent (AED)'))
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('Calculated from the contract period and the base rent — a full year equals the base rent.')),
                        Select::make('multiple_rent_amount')
                            ->label(__('Multiple Rent Amount'))
                            ->options(YesNo::class)
                            ->default(YesNo::NO->value)
                            ->required(),
                        TextInput::make('security_deposit_amount')
                            ->label(__('Security Deposit (AED)'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0),
                    ])->columns(3),

                Section::make(__('Contract Identification'))
                    ->description(__('Fields required by the property\'s emirate for its own tenancy contract format (Ejari, Sharjawi, …).'))
                    ->schema([
                        TextInput::make('government_contract_number')
                            ->label(__('Contract No.'))
                            ->maxLength(255),
                        Placeholder::make('contract_type_detected')
                            ->label(__('Contract Type'))
                            ->content(fn (Get $get): string => Lease::detectContractType((array) $get('units'))?->getLabel() ?? '—')
                            ->helperText(__('Detected from the unit\'s rental type.')),
                        Select::make('payment_method')
                            ->label(__('Payment Method'))
                            ->options(InstallmentPaymentMethod::class),
                        TextInput::make('number_of_payments')
                            ->label(__('No. of Payments'))
                            ->numeric()
                            ->minValue(0),
                        Select::make('condition_template_id')
                            ->label(__('Conditions Template'))
                            ->options(fn (): array => ConditionTemplate::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->helperText(__('Auto-suggested from the selected units\' property once chosen below — change it if a different template applies.')),
                    ])->columns(3),

                Section::make(__('Occupancy & Use'))
                    ->schema([
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
                            ->label(__('Authority No.'))
                            ->maxLength(255),
                        TextInput::make('poa_identification_number')
                            ->label(__('EID No. / Trade License No.'))
                            ->maxLength(255),
                        TextInput::make('poa_unified_number')
                            ->label(__('Unified No.'))
                            ->maxLength(255),
                        TextInput::make('poa_name')
                            ->label(__('Name'))
                            ->maxLength(255),
                    ])->columns(2),

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
                            ->table([
                                TableColumn::make(__('Party')),
                                TableColumn::make(__('Role')),
                            ])
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
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?array $state) => $set(
                                'condition_template_id',
                                self::suggestConditionTemplateId($state ?? []),
                            ))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function syncAnnualRent(Set $set, mixed $start, mixed $end, mixed $baseRent): void
    {
        $annual = Lease::annualRentFor($start, $end, $baseRent);

        $set('annual_rent', $annual === null ? null : number_format($annual, 2, '.', ''));
    }

    /**
     * Suggests the conditions template matching the first selected unit's
     * property (emirate + commercial-vs-residential classification) — the
     * office can still override the Select afterward.
     *
     * @param  list<int>  $unitIds
     */
    private static function suggestConditionTemplateId(array $unitIds): ?int
    {
        $unit = Unit::query()->with('property')->find($unitIds[0] ?? null);
        $property = $unit?->property;

        if ($unit === null || $property === null || $property->emirate === null) {
            return null;
        }

        $format = match (true) {
            $property->emirate === Emirate::DUBAI => 'dubai_ejari',
            in_array($unit->property_classification, [PropertyClassification::COMMERCIAL, PropertyClassification::INDUSTRIAL], true) => 'sharjah_commercial',
            default => 'sharjah_residential',
        };

        return ConditionTemplate::where('contract_format', $format)->value('id');
    }
}
