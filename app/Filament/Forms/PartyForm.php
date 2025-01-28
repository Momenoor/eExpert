<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class PartyForm
{
    public static function make(): array
    {
        return [
            Group::make([
                TextInput::make('name')->required(),
                TextInput::make('email')->required(),
                TextInput::make('mobile')->required(),
                TextInput::make('fax')->required(),
                TextInput::make('address')->required(),
                TextInput::make('user_id')->required(),
                Select::make('user_id')->relationship('user', 'name')->required()
            ])->relationship('entity'),
            Select::make('party_type_id')->relationship('PartyType', 'name')->required()
        ];
    }
}
