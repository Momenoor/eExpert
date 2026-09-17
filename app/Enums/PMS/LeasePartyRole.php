<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeasePartyRole: string implements HasColor, HasLabel
{
    case PRIMARY_TENANT = 'primary_tenant';
    case CO_TENANT = 'co_tenant';
    case GUARANTOR = 'guarantor';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PRIMARY_TENANT => __('Primary Tenant'),
            self::CO_TENANT => __('Co-Tenant'),
            self::GUARANTOR => __('Guarantor'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PRIMARY_TENANT => 'success',
            self::CO_TENANT => 'info',
            self::GUARANTOR => 'warning',
        };
    }
}
