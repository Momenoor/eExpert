<?php

namespace App\Filament\Mms\Resources\Courts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourtForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required(),
                TextInput::make('phone')
                    ->label(__('Phone')),
                TextInput::make('email')
                    ->label(__('Email address')),
                Textarea::make('address')
                    ->label(__('Address')),
            ]);
    }
}
