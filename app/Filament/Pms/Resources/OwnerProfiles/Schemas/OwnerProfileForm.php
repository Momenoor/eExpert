<?php

namespace App\Filament\Pms\Resources\OwnerProfiles\Schemas;

use App\Models\OwnerGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Creates the owner's `Party` record and `OwnerProfile` together in one
 * screen — see `TenantForm` for why: an owner being onboarded here
 * has, in almost every case, no existing Party record to pick from yet.
 */
class OwnerProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Owner'))
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
                        TextInput::make('identification_number')
                            ->label(__('Identification Number (Emirates ID / Passport)'))
                            ->maxLength(255),
                        TextInput::make('trn')
                            ->label(__('TRN'))
                            ->maxLength(255),
                        Select::make('owner_group_id')
                            ->label(__('Owner Group'))
                            ->options(fn (): array => OwnerGroup::orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->columnSpanFull()
                            ->helperText(__('For an owner administered as part of a shared estate — e.g. "Legal Heirs of Mahmoud Kalbat" — so a contract shows the group\'s name rather than every heir separately.')),
                    ])->columns(2),

                Section::make(__('Banking'))
                    ->schema([
                        TextInput::make('bank_name')
                            ->label(__('Bank Name'))
                            ->maxLength(255),
                        TextInput::make('bank_account_no')
                            ->label(__('Account No'))
                            ->maxLength(255),
                        TextInput::make('iban')
                            ->label(__('IBAN'))
                            ->maxLength(34)
                            ->rule('regex:/^AE\d{21}$/')
                            ->validationMessages([
                                'regex' => __('A UAE IBAN is AE followed by 21 digits.'),
                            ])
                            ->placeholder('AE070331234567890123456'),
                    ])->columns(3),
            ]);
    }
}
