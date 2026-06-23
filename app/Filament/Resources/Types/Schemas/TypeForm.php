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
                Toggle::make('active')
                    ->label(__('Active'))
                    ->default(true)
                    ->required(),
                Toggle::make('allow_current_status_import')
                    ->label(__('Allow Current Status Import'))
                    ->helperText(__('If enabled, matters can be imported for incentives even if not final reported, as long as they have collected fees.'))
                    ->default(false),
            ]);
    }
}
