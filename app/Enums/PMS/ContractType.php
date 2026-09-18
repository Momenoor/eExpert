<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel
{
    case COMMERCIAL = 'commercial';
    case RESIDENTIAL = 'residential';
    case INDUSTRIAL = 'industrial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::COMMERCIAL => __('Commercial'),
            self::RESIDENTIAL => __('Residential'),
            self::INDUSTRIAL => __('Industrial'),
        };
    }
}
