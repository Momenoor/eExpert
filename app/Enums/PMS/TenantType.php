<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether a tenant is an individual or a corporate entity.
 *
 * Drives which identification a tenant is expected to carry: a PERSON holds
 * an Emirates ID/GCC ID/passport, a COMPANY a trade license — the form should
 * steer `identification_type` accordingly, but this is guidance, not an
 * enforced pairing, since a company can still be identified by an
 * authorised signatory's personal ID on some contracts.
 */
enum TenantType: string implements HasColor, HasLabel
{
    case PERSON = 'person';
    case COMPANY = 'company';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PERSON => __('Person'),
            self::COMPANY => __('Company'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PERSON => 'success',
            self::COMPANY => 'info',
        };
    }
}
