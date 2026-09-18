<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractCategory: string implements HasColor, HasLabel
{
    case NEW = 'new';
    case RENEWAL = 'renewal';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NEW => __('New'),
            self::RENEWAL => __('Renewal'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NEW => 'success',
            self::RENEWAL => 'info',
        };
    }
}
