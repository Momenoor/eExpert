<?php

namespace App\Filament\Resources\PayrollRuns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class PayrollRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Payroll Run'))
                    ->schema([
                        Select::make('period')
                            ->label(__('Period'))
                            // A closed list rather than a free-text month, because
                            // the period string is matched character-for-character
                            // against loan instalment due periods.
                            ->options(fn () => self::periodOptions())
                            ->default(now()->subMonth()->format('Y-m'))
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->validationMessages([
                                'unique' => __('A payroll run already exists for this month.'),
                            ]),
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->maxLength(255)
                            ->placeholder(__('Optional label, e.g. "March salaries"')),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    /**
     * The last two years of months, newest first.
     *
     * @return array<string, string>
     */
    private static function periodOptions(): array
    {
        $options = [];
        $month = Carbon::now()->startOfMonth()->addMonth();

        for ($i = 0; $i < 24; $i++) {
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
            $month = $month->subMonth();
        }

        return $options;
    }
}
