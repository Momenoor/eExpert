<?php

namespace App\Filament\Forms;

use App\Forms\Components\CheckboxListTree;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CourtForm
{
    public static function make(): array
    {
        return [
            Group::make([
                ...\App\Filament\Forms\EntityForm::make()
            ])->relationship('entity'),
            CheckboxList::make('levels')
                ->relationship('levels', 'name')
        ];
    }
}
