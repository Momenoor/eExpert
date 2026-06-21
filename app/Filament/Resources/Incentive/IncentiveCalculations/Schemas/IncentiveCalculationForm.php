<?php

namespace App\Filament\Resources\Incentive\IncentiveCalculations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\Party;

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

                Section::make(__('Assistant Adjustments'))
                    ->description(__('Add fixed deductions for specific assistants in this period.'))
                    ->schema([
                        Repeater::make('assistantExtras')
                            ->relationship('assistantExtras')
                            ->schema([
                                Select::make('party_id')
                                    ->label(__('Assistant'))
                                    ->options(Party::whereJsonContains('role', ['role' => 'expert', 'type' => 'assistant'])
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('fixed_deduction')
                                    ->label(__('Fixed Deduction (AED)'))
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('AED'),
                            ])
                            ->columns(2)
                            ->itemLabel(fn(array $state): ?string => Party::find($state['party_id'])?->name ?? null)
                            ->defaultItems(0)
                            ->reorderable(false),
                    ])
                    ->visible(fn($record) => $record?->exists ?? false),
            ]);
    }
}
