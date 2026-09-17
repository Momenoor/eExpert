<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttestationStatus: string implements HasColor, HasLabel
{
    case UNREGISTERED = 'unregistered';
    case PENDING_DOCUMENTS = 'pending_documents';
    case SUBMITTED = 'submitted';
    case REGISTERED = 'registered';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UNREGISTERED => __('Unregistered'),
            self::PENDING_DOCUMENTS => __('Pending Documents'),
            self::SUBMITTED => __('Submitted'),
            self::REGISTERED => __('Registered'),
            self::CANCELLED => __('Cancelled'),
            self::EXPIRED => __('Expired'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNREGISTERED => 'gray',
            self::PENDING_DOCUMENTS => 'warning',
            self::SUBMITTED => 'info',
            self::REGISTERED => 'success',
            self::CANCELLED, self::EXPIRED => 'danger',
        };
    }
}
