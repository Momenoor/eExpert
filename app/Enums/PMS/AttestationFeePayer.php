<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttestationFeePayer: string implements HasColor, HasLabel
{
    case TENANT = 'tenant';
    case LANDLORD = 'landlord';
    case SHARED = 'shared';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TENANT => __('Tenant'),
            self::LANDLORD => __('Landlord'),
            self::SHARED => __('Shared'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TENANT => 'info',
            self::LANDLORD => 'warning',
            self::SHARED => 'gray',
        };
    }
}
