<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MatterLevel: string implements HasColor, HasLabel
{
    case FIRST_INSTANCE = 'first_instance';
    case APPEAL = 'appeal';
    case CONGESTION = 'congestion';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIRST_INSTANCE => __('First Instance'),
            self::APPEAL => __('Appeal'),
            self::CONGESTION => __('Congestion'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FIRST_INSTANCE => 'info',
            self::APPEAL => 'success',
            self::CONGESTION => 'warning',
        };
    }
}
