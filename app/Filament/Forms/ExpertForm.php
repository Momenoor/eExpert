<?php

namespace App\Filament\Forms;

use App\Forms\Components\CheckboxListTree;
use App\Models\ExpertType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpertForm
{
    public static function make(): array
    {
        return [
            Group::make([
                ...\App\Filament\Forms\EntityForm::make()
            ])->relationship('entity'),
            Select::make('expertise_field_id')
                ->relationship('expertiseField', 'name')
                ->createOptionForm(\App\Filament\Forms\ExpertiseFieldForm::make())
                ->editOptionForm(\App\Filament\Forms\ExpertiseFieldForm::make())
                ->searchable()
                ->preload()
                ->live(),
            CheckboxListTree::make('types')
                ->searchable()
                ->label('Types')
                ->relationship('types', 'name')
                ->withChildren()
            ,
        ];
    }
}
