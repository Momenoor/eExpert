<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasLabel;

enum DesignatedUse: string implements HasLabel
{
    case RESIDENTIAL = 'residential';
    case COMMERCIAL = 'commercial';
    case INDUSTRIAL = 'industrial';
    case WAREHOUSE = 'warehouse';
    case RETAIL = 'retail';
    case OFFICE = 'office';
    case SHOWROOM = 'showroom';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RESIDENTIAL => __('Residential'),
            self::COMMERCIAL => __('Commercial'),
            self::INDUSTRIAL => __('Industrial'),
            self::WAREHOUSE => __('Warehouse'),
            self::RETAIL => __('Retail'),
            self::OFFICE => __('Office'),
            self::SHOWROOM => __('Showroom'),
        };
    }
}
