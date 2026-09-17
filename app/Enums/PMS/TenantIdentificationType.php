<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TenantIdentificationType: string implements HasColor, HasLabel
{
    case EMIRATES_ID = 'emirates_id';
    case GCC_ID = 'gcc_id';
    case PASSPORT_WITH_VISA = 'passport_with_visa';
    case TRADE_LICENSE = 'trade_license';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EMIRATES_ID => __('Emirates ID'),
            self::GCC_ID => __('GCC ID'),
            self::PASSPORT_WITH_VISA => __('Passport with Visa'),
            self::TRADE_LICENSE => __('Trade License'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EMIRATES_ID => 'success',
            self::GCC_ID => 'info',
            self::PASSPORT_WITH_VISA => 'warning',
            self::TRADE_LICENSE => 'gray',
        };
    }
}
