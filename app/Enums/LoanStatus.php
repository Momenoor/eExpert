<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasColor, HasLabel
{
    case ACTIVE = 'active';
    case SETTLED = 'settled';
    case WRITTEN_OFF = 'written_off';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::SETTLED => __('Settled'),
            self::WRITTEN_OFF => __('Written Off'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'warning',
            self::SETTLED => 'success',
            self::WRITTEN_OFF => 'danger',
        };
    }
}
