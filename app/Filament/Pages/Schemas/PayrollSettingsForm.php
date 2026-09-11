<?php

namespace App\Filament\Pages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The statutory and office-policy figures the payroll engine calculates from.
 *
 * Every field here mirrors a `DEFAULT_*` constant in the corresponding service
 * (LoanScheduleService, EndOfServiceGratuityService, LeaveEntitlementService) —
 * this form only ever overrides those defaults via Setting::get(); it never
 * introduces a figure the code does not already know how to fall back to.
 */
class PayrollSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Loan & Petty Cash Recovery'))
                ->description(__('Governs how staff loan and petty cash instalments are rounded.'))
                ->icon(Heroicon::Banknotes)
                ->columns(2)
                ->schema([
                    TextInput::make('payroll_loan_rounding_step')
                        ->label(__('Instalment Rounding Step (AED)'))
                        ->suffix('AED')
                        ->numeric()
                        ->minValue(1)
                        ->step(1)
                        ->required()
                        ->default(50)
                        ->helperText(__('Every instalment after the first is rounded down to the nearest multiple of this amount; the remainder is charged in month one.')),
                ]),

            Section::make(__('Journal Voucher'))
                ->description(__('Office costs added to the monthly Salaries journal voucher, alongside the payslip figures.'))
                ->icon(Heroicon::Banknotes)
                ->columns(2)
                ->schema([
                    TextInput::make('payroll_bank_fee_amount')
                        ->label(__('Bank Transfer Fee (AED)'))
                        ->suffix('AED')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->default(0)
                        ->helperText(__('A fixed charge the bank makes for the salary transfer batch, posted once per run regardless of headcount.')),
                ]),

            Section::make(__('End of Service Gratuity — Federal Decree-Law 33/2021, Article 51'))
                ->description(__('These are statutory figures, not office policy. Only change them if the law itself changes, or on legal advice — lowering them below the statutory minimum exposes the office to liability.'))
                ->icon(Heroicon::Scale)
                ->columns(2)
                ->schema([
                    TextInput::make('payroll_days_per_month')
                        ->label(__('Days Per Month (for daily-rate conversion)'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(31)
                        ->required()
                        ->default(30)
                        ->helperText(__('Basic salary ÷ this figure gives the daily rate used for both gratuity and unpaid-leave deductions — the two must never disagree about what a month is.')),

                    TextInput::make('payroll_eosg_cap_months')
                        ->label(__('Statutory Cap (months of basic pay)'))
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->default(24)
                        ->helperText(__('The gratuity total can never exceed this many months of basic pay, however long the service.')),

                    TextInput::make('payroll_eosg_days_per_year_first_five')
                        ->label(__('Days Accrued Per Year — First 5 Years'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(21),

                    TextInput::make('payroll_eosg_days_per_year_after_five')
                        ->label(__('Days Accrued Per Year — Beyond 5 Years'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(30),
                ]),

            Section::make(__('Annual Leave — Federal Decree-Law 33/2021, Article 29'))
                ->description(__('Statutory minimums for paid annual leave.'))
                ->icon(Heroicon::Sun)
                ->columns(2)
                ->schema([
                    TextInput::make('payroll_annual_leave_days')
                        ->label(__('Annual Leave (days per completed year)'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(30),

                    TextInput::make('payroll_annual_leave_days_per_month')
                        ->label(__('Accrual Rate (days per month, months 6–12)'))
                        ->numeric()
                        ->minValue(0)
                        ->step(0.1)
                        ->required()
                        ->default(2),
                ]),

            Section::make(__('Sick Leave — Federal Decree-Law 33/2021, Article 31'))
                ->description(__('The three pay bands a sick-leave request is split across, in order.'))
                ->icon(Heroicon::Heart)
                ->columns(3)
                ->schema([
                    TextInput::make('payroll_sick_full_pay_days')
                        ->label(__('Full-Pay Days'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(15),

                    TextInput::make('payroll_sick_half_pay_days')
                        ->label(__('Half-Pay Days'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(30),

                    TextInput::make('payroll_sick_unpaid_days')
                        ->label(__('Unpaid Days'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(45)
                        ->helperText(__('Past this many sick days in one service year, the statute stops providing for sick leave; anything further is ordinary unpaid leave.')),
                ]),
        ]);
    }
}
