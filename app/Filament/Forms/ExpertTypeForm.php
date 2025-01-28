<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ExpertTypeForm
{
    public static function make(): array
    {
        return [
            TextInput::make('name')->required(),
            Select::make('parent_id')
                ->relationship('parent', 'name')
                ->searchable(),
        ];
    }
}
