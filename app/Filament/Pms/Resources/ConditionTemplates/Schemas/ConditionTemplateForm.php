<?php

namespace App\Filament\Pms\Resources\ConditionTemplates\Schemas;

use App\Enums\PMS\Emirate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConditionTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Conditions Template'))
                    ->description(__('A reusable set of Special Conditions the office can attach to a lease — General Conditions are fixed statutory text and are not managed here.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('emirate')
                            ->label(__('Emirate'))
                            ->options(Emirate::class),
                        Select::make('contract_format')
                            ->label(__('Contract Format'))
                            ->options([
                                'sharjah_commercial' => __('Sharjah Commercial'),
                                'sharjah_residential' => __('Sharjah Residential'),
                                'dubai_ejari' => __('Dubai EJARI'),
                            ])
                            ->required(),
                    ])->columns(3),
            ]);
    }
}
