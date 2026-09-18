<?php

namespace App\Filament\Pms\Resources\Tenants\Schemas;

use App\Enums\PMS\TenantIdentificationType;
use App\Enums\PMS\TenantType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Creates the tenant's `Party` record and `Tenant` together in one
 * screen — `name`/`phone`/`email` here are Party fields, not Tenant
 * columns; the page classes (Create/EditTenant) split them back apart.
 * A tenant is, in the overwhelming majority of cases, someone with no other
 * relationship to the office yet, so making them pick an already-existing
 * Party first would be a step nobody has a party to select for.
 */
class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Tenant'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Full Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TagsInput::make('phone')
                            ->label(__('Phone'))
                            ->trim()
                            ->splitKeys(['Tab', ' ', ',', 'Enter']),
                        TagsInput::make('email')
                            ->label(__('Email'))
                            ->trim()
                            ->splitKeys(['Tab', ' ', ',', 'Enter'])
                            ->nestedRecursiveRules(['email']),
                        Select::make('tenant_type')
                            ->label(__('Tenant Type'))
                            ->options(TenantType::class)
                            ->default(TenantType::PERSON->value)
                            ->required()
                            ->live(),
                        Select::make('identification_type')
                            ->label(__('Identification Type'))
                            ->options(TenantIdentificationType::class)
                            ->default(fn ($get) => $get('tenant_type') === TenantType::COMPANY->value
                                ? TenantIdentificationType::TRADE_LICENSE->value
                                : TenantIdentificationType::EMIRATES_ID->value)
                            ->required(),
                        TextInput::make('identification_number')
                            ->label(__('Identification Number'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('unified_number')
                            ->label(__('Unified No.'))
                            ->maxLength(255),
                        TextInput::make('nationality')
                            ->label(__('Nationality'))
                            ->maxLength(255),
                        TextInput::make('trn')
                            ->label(__('TRN'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('tenant_type') === TenantType::COMPANY->value)
                            ->helperText(__('Only a corporate tenant carries a Tax Registration Number.')),
                        TextInput::make('emergency_contact_name')
                            ->label(__('Emergency Contact Name'))
                            ->maxLength(255),
                        TextInput::make('emergency_contact_phone')
                            ->label(__('Emergency Contact Phone'))
                            ->tel()
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }
}
