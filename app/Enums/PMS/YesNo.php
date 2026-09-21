<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum YesNo: string implements HasColor, HasLabel
{
    case YES = 'yes';
    case NO = 'no';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::YES => __('Yes'),
            self::NO => __('No'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::YES => 'success',
            self::NO => 'gray',
        };
    }
}
