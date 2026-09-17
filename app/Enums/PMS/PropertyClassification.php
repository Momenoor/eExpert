<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How a unit is classified for VAT purposes.
 *
 * Residential rent is VAT-exempt; commercial rent is standard-rated at 5%;
 * mixed-use splits between the two. Unit::vatRate() is the only place this
 * classification is translated into an actual rate — nothing else should
 * re-derive VAT from a raw string comparison.
 */
enum PropertyClassification: string implements HasColor, HasLabel
{
    case RESIDENTIAL = 'residential';
    case COMMERCIAL = 'commercial';
    case INDUSTRIAL = 'industrial';
    case MIXED_USE = 'mixed_use';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RESIDENTIAL => __('Residential'),
            self::COMMERCIAL => __('Commercial'),
            self::INDUSTRIAL => __('Industrial'),
            self::MIXED_USE => __('Mixed Use'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RESIDENTIAL => 'success',
            self::COMMERCIAL => 'info',
            self::INDUSTRIAL => 'warning',
            self::MIXED_USE => 'danger',
        };
    }
}
