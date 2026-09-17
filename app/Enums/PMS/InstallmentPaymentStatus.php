<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InstallmentPaymentStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PARTIAL = 'partial';
    case OVERDUE = 'overdue';
    case BOUNCED = 'bounced';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::PAID => __('Paid'),
            self::PARTIAL => __('Partial'),
            self::OVERDUE => __('Overdue'),
            self::BOUNCED => __('Bounced'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PAID => 'success',
            self::PARTIAL => 'warning',
            self::OVERDUE => 'danger',
            self::BOUNCED => 'danger',
        };
    }
}
