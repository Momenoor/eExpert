<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UnitStatus: string implements HasColor, HasLabel
{
    case VACANT = 'vacant';
    case OCCUPIED = 'occupied';
    case UNDER_MAINTENANCE = 'under_maintenance';
    case RESERVED = 'reserved';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::VACANT => __('Vacant'),
            self::OCCUPIED => __('Occupied'),
            self::UNDER_MAINTENANCE => __('Under Maintenance'),
            self::RESERVED => __('Reserved'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::VACANT => 'success',
            self::OCCUPIED => 'info',
            self::UNDER_MAINTENANCE => 'danger',
            self::RESERVED => 'warning',
        };
    }
}
