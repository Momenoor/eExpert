<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class UserForm
{
    public static function make($initialValue = ['name' => null, 'email' => null]): array
    {
        return [
            TextInput::make('name')->required()->readOnly((isset($initialValue['name'])))->default($initialValue['name']),
            TextInput::make('email')->required()->email()->readOnly((isset($initialValue['email'])))->default($initialValue['email'])->unique(),
        ];
    }
}
