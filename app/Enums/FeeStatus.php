<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeeStatus: string implements HasColor, HasLabel
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERPAID = 'overpaid';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UNPAID => __('Unpaid'),
            self::PARTIAL => __('Partial'),
            self::PAID => __('Paid'),
            self::OVERPAID => __('Overpaid'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
            self::OVERPAID => 'info',
        };
    }
}
