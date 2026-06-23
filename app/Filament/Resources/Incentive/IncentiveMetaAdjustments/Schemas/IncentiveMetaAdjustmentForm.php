<?php

namespace App\Filament\Resources\Incentive\IncentiveMetaAdjustments\Schemas;

use App\Models\MatterFieldDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IncentiveMetaAdjustmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('field_name')
                    ->label(__('Field Name'))
                    ->options(MatterFieldDefinition::pluck('label', 'label'))
                    ->required()
                    ->searchable(),
                TextInput::make('field_value')
                    ->label(__('Field Value'))
                    ->placeholder(__('Optional: match specific value')),
                TextInput::make('percentage_adjustment')
                    ->label(__('Percentage Adjustment'))
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->helperText(__('Positive to increase, negative to decrease.')),
            ]);
    }
}
