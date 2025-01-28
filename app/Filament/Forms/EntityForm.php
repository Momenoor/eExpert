<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use App\Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;


class EntityForm
{
    public static function make(): array
    {
        return [
            TextInput::make('name')->required(),
            TextInput::make('email')->required(),
            TextInput::make('mobile'),
            TextInput::make('fax'),
            Textarea::make('address'),
            Section::make('user')->description(new HtmlString('<div> Create an user and assign it to the entity OR Select from pre-exist users. <br>Default password is <span class="font-bold">password.</span></div>'))->schema([
                Select::make('user_id')->relationship('user', 'name')->createOptionForm(function (Get $get) {
                    $initialValues = [
                        'name' => $get('name'),
                        'email' => $get('email'),
                    ];
                    return \App\Filament\Forms\UserForm::make($initialValues);
                })->editOptionForm(\App\Filament\Forms\UserForm::make())
                    ->searchable()
                    ->preload()
                    ->live()

            ])
        ];
    }
}
