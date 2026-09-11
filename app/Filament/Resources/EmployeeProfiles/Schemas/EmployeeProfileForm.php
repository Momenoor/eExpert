<?php

namespace App\Filament\Resources\EmployeeProfiles\Schemas;

use App\Models\Party;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Employment'))
                    ->schema([
                        Select::make('party_id')
                            ->label(__('Employee'))
                            ->options(fn () => Party::withRole('employee')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->unique(ignoreRecord: true)
                            // Only parties already marked as employees appear. The
                            // role is set on the party record itself, so payroll
                            // and the party list can never disagree about who is
                            // on staff.
                            ->helperText(__('Only parties holding the Employee role are listed.')),
                        TextInput::make('employee_no')
                            ->label(__('Employee Number'))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('designation')
                            ->label(__('Designation'))
                            ->maxLength(255),
                        TextInput::make('display_name')
                            ->label(__('Name as WPS From'))
                            ->maxLength(255),
                        DatePicker::make('date_of_joining')
                            ->label(__('Date of Joining'))
                            ->required()
                            // Gratuity and the leave-year anniversary are both
                            // measured from this date, which is why it is required
                            // here even though the column is nullable for imports.
                            ->helperText(__('Gratuity and leave entitlements are calculated from this date.')),
                        DatePicker::make('date_of_leaving')
                            ->label(__('Date of Leaving'))
                            ->afterOrEqual('date_of_joining')
                            ->helperText(__('Leave blank while employed.')),
                    ])->columns(3),

                Section::make(__('Identity Documents'))
                    ->schema([
                        TextInput::make('passport_no')
                            ->label(__('Passport No'))
                            ->maxLength(255),
                        DatePicker::make('passport_expiry')
                            ->label(__('Passport Expiry')),
                        TextInput::make('emirates_id_no')
                            ->label(__('Emirates ID No'))
                            ->placeholder('784-1990-1234567-1')
                            ->maxLength(20),
                        DatePicker::make('emirates_id_expiry')
                            ->label(__('Emirates ID Expiry')),
                        TextInput::make('labour_card_no')
                            ->label(__('Labour Card No'))
                            ->maxLength(255),
                        TextInput::make('mohre_personal_no')
                            ->label(__('MOHRE Personal Number'))
                            ->maxLength(20)
                            ->helperText(__('The employee key used in the WPS salary file.')),
                        DatePicker::make('labour_card_expiry')
                            ->label(__('Labour Card Expiry')),
                    ])->columns(3),

                Section::make(__('Residency & Visa'))
                    ->schema([
                        TextInput::make('residency_visa_no')
                            ->label(__('Visa No'))
                            ->maxLength(255),
                        TextInput::make('visa_file_no')
                            ->label(__('File No'))
                            ->maxLength(255),
                        DatePicker::make('residency_expiry')
                            ->label(__('Residency Expiry')),
                        TextInput::make('sponsor_name')
                            ->label(__('Sponsor'))
                            ->maxLength(255),
                    ])->columns(3),

                Section::make(__('Banking & WPS'))
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
                            // A UAE IBAN is AE plus 21 digits, always. Catching a
                            // mistyped one here is the difference between a
                            // rejected transfer and a salary paid to a stranger.
                            ->rule('regex:/^AE\d{21}$/')
                            ->validationMessages([
                                'regex' => __('A UAE IBAN is AE followed by 21 digits.'),
                            ])
                            ->placeholder('AE070331234567890123456'),
                        TextInput::make('wps_routing_code')
                            ->label(__('Routing Code'))
                            ->maxLength(9)
                            ->rule('regex:/^\d{9}$/')
                            ->validationMessages([
                                'regex' => __('A routing code is exactly 9 digits.'),
                            ]),
                    ])->columns(2),

                Section::make(__('Gratuity & Leave'))
                    ->schema([
                        Toggle::make('is_eosg_applicable')
                            ->label(__('Applicable for EOSG'))
                            ->default(true)
                            ->helperText(__('Off excludes this employee from the annual gratuity accrual and closing voucher.')),
                        TextInput::make('opening_leave_balance')
                            ->label(__('Opening Leave Balance (days)'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('Days carried over from before leave was tracked here, added once to their first year of entitlement.')),
                        TextInput::make('opening_eosg_balance')
                            ->label(__('Opening EOSG Balance (AED)'))
                            ->suffix('AED')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0)
                            ->helperText(__('Gratuity earned before this system tracked payroll, entered once and added to whichever closing voucher is generated first for this employee.')),
                    ])->columns(2),
            ]);
    }
}
