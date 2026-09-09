<?php

namespace App\Filament\Resources\EmployeeLoans\Schemas;

use App\Enums\LoanKind;
use App\Models\Party;
use App\Services\LoanScheduleService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmployeeLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Advance'))
                    ->schema([
                        Select::make('party_id')
                            ->label(__('Employee'))
                            ->options(fn () => Party::withRole('employee')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('kind')
                            ->label(__('Type'))
                            ->options(LoanKind::class)
                            ->default(LoanKind::LOAN->value)
                            ->required(),
                        TextInput::make('principal')
                            ->label(__('Amount (AED)'))
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('months')
                            ->label(__('Months'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->live(onBlur: true),
                        DatePicker::make('starts_on')
                            ->label(__('First Instalment'))
                            ->default(now()->addMonth()->startOfMonth())
                            ->required(),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make(__('Repayment Schedule'))
                    // Shown before saving, because the 50 AED rounding can change
                    // the term: 300 over twelve months is not twelve instalments
                    // of 25, it is six of 50. Better to see that here than to
                    // discover it on the first payslip.
                    ->description(__('Instalments are rounded down to whole 50 AED. The remainder is charged in the first month.'))
                    ->schema([
                        Placeholder::make('schedule_preview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string => self::preview(
                                (float) $get('principal'),
                                (int) $get('months'),
                            ))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => filled($get('principal')) && filled($get('months'))),
            ]);
    }

    private static function preview(float $principal, int $months): string
    {
        if ($principal <= 0 || $months < 1) {
            return __('Enter an amount and a term to preview the schedule.');
        }

        $schedule = app(LoanScheduleService::class)->plan($principal, $months, now()->format('Y-m'));

        $first = $schedule[0]['amount'];
        $rest = $schedule[1]['amount'] ?? null;
        $count = count($schedule);

        if ($rest === null) {
            return __(':count instalment of :first AED.', [
                'count' => $count,
                'first' => number_format($first, 2),
            ]);
        }

        return __('Month 1: :first AED, then :rest AED for :remaining months (:count months total).', [
            'first' => number_format($first, 2),
            'rest' => number_format($rest, 2),
            'remaining' => $count - 1,
            'count' => $count,
        ]);
    }
}
