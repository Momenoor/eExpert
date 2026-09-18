<?php

namespace App\Filament\Pms\Resources\LeasePrintTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeasePrintTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Print Template'))
                    ->description(__('One background-image page per contract page — upload the actual government form and place each field on top of it.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('contract_format')
                            ->label(__('Contract Format'))
                            ->options([
                                'sharjah_commercial' => __('Sharjah Commercial'),
                                'sharjah_residential' => __('Sharjah Residential'),
                                'dubai_ejari' => __('Dubai EJARI'),
                            ])
                            ->required()
                            ->disabledOn('edit'),
                    ])->columns(2),
            ]);
    }
}
