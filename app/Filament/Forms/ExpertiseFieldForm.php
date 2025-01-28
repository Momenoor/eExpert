<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ExpertiseFieldForm
{
    public static function make(): array
    {
        return [
            TextInput::make('name')
        ];
    }
}
