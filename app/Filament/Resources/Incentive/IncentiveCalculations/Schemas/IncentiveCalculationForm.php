<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncentiveCalculationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Calculation Period'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Calculation Name'))
                            ->required()
                            ->placeholder(__('e.g. Bi-Monthly Incentive — Aug/Sep 2025'))
                            ->columnSpanFull(),
                        DatePicker::make('period_start')
                            ->label(__('Period Start'))
                            ->required(),
                        DatePicker::make('period_end')
                            ->label(__('Period End'))
                            ->required()
                            ->afterOrEqual('period_start'),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
