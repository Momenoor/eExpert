<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MatterCollectionStatus: string implements HasColor, HasLabel
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case NO_FEES = 'no_fees';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UNPAID => __('Unpaid'),
            self::PARTIAL => __('Partial'),
            self::PAID => __('Paid'),
            self::NO_FEES => __('No Fees'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
            self::NO_FEES => 'gray',
        };
    }
}
