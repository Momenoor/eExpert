<?php

namespace App\Filament\Forms\Components;

use App\Enum\ActiveStatusEnum;
use Filament\Forms\Components\ToggleButtons;

class StatusToggle extends ToggleButtons
{
    public static function make(string $name = 'status'): static
    {
        return parent::make($name)
            ->columnSpanFull()
            ->options(ActiveStatusEnum::class)
            ->default(ActiveStatusEnum::Active->value)
            ->grouped();
    }
}
