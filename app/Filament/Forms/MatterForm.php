<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;

class MatterForm
{
    public static function make(): array
    {
        return [
            TextInput::make('year')->required(),
            TextInput::make('number')->required(),
            DatePicker::make('received_at')->required(),
            DatePicker::make('next_session_at')->required(),
            Select::make('court_id')->relationship('court', 'id')->required()->createOptionForm(\App\Filament\Forms\CourtForm::make())->getOptionLabelFromRecordUsing(fn($record) => $record->entity ? $record->entity->name : ''),
            Select::make('court_level_id')->relationship('courtLevel', 'name')->required(),
            //Select::make('matter_status_id')->relationship('matterStatus', 'name')->required(),
            //Select::make('matter_type_id')->relationship('matterType', 'name')->required(),

        ];
    }
}
