<?php

namespace App\Filament\Resources\Incentive\MatterTypeIncentiveConfigs\Schemas;

use App\Enums\MatterCommissiong;
use App\Enums\MatterDifficulty;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MatterTypeIncentiveConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Matter Type Configuration'))
                    ->schema([
                        TextInput::make('name')->label(__('Name'))->required(),
                        Select::make('calculation_type')
                            ->label(__('Calculation Type'))
                            ->options([
                                'tiered' => __('Tiered — % by working days & difficulty'),
                                'fixed' => __('Fixed — fixed % for all fees'),
                                'committee' => __('Committee — tiered ± 2%'),
                            ])
                            ->required()
                            ->live()
                            ->helperText(__('Tiered: working days from received date to initial report. Fixed: e.g. liquidation/insolvency at 8%.')),

                        TextInput::make('assistant_rate')
                            ->label(__('Assistant Rate (%)'))
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->default(100)
                            ->helperText(__('% of base incentive distributed among assistants on the matter equally')),

                        TextInput::make('fixed_percentage')
                            ->label(__('Fixed Percentage (%)'))
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Get $get) => $get('calculation_type') === 'fixed')
                            ->required(fn (Get $get) => $get('calculation_type') === 'fixed')
                            ->helperText(__('e.g. 8 for 8% on all fees for this matter type')),

                    ])->columns(2),

                Section::make(__('Completion Day Tiers'))
                    ->description(__('Working days from received date to initial report, by difficulty level'))
                    ->visible(fn (Get $get) => in_array($get('calculation_type'), ['tiered', MatterCommissiong::COMMITTEE->value]))
                    ->schema([
                        Repeater::make('tiers')
                            ->relationship()
                            ->label(__('Tiers'))
                            ->schema([
                                Select::make('difficulty')
                                    ->label(__('Difficulty'))
                                    ->options(MatterDifficulty::class)
                                    ->required(),
                                TextInput::make('days_from')
                                    ->label(__('Days From'))
                                    ->numeric()->required()->minValue(0),
                                TextInput::make('days_to')
                                    ->label(__('Days To'))
                                    ->numeric()->minValue(0)
                                    ->placeholder(__('Leave empty for no upper limit')),
                                TextInput::make('percentage')
                                    ->label(__('Incentive %'))
                                    ->suffix('%')->numeric()->required()->minValue(0)->maxValue(100),
                            ])
                            ->columns(4)
                            ->addActionLabel(__('Add Tier'))
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->helperText(__('Per PDF: Simple 1–12 = 9%, 13–15 = 7%, 16–18 = 5%, 19–20 = 3%, 21–25 = 1%')),
                    ]),
            ]);
    }
}
