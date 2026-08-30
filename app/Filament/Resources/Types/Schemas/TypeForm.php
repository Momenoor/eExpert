<?php

namespace App\Filament\Resources\Types\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required(),
                Select::make('incentive_config_id')
                    ->label(__('Incentive Configuration'))
                    ->relationship('incentiveConfig', 'name')
                    ->searchable(),
                Select::make('incentive_trigger_type')
                    ->label(__('Incentive Trigger Type'))
                    ->options([
                        'final_report_date' => __('Matter Final Reported'),
                        'fees_registered_date' => __('Fee Registered'),
                    ])
                    ->required(),
                Toggle::make('active')
                    ->label(__('Active'))
                    ->default(true)
                    ->required(),
                Toggle::make('allow_current_status_import')
                    ->label(__('Allow Current Status Import'))
                    ->helperText(__('If enabled, matters can be imported for incentives even if not final reported, as long as they have collected fees.'))
                    ->default(false),
                Toggle::make('exclude_from_incentive_count')
                    ->label(__('Exclude from Incentive Count'))
                    ->helperText(__('If enabled, matters of this type will not be counted towards the monthly total for extra incentive percentages.'))
                    ->default(false),
            ]);
    }
}
