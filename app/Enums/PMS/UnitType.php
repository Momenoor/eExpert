<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UnitType: string implements HasColor, HasLabel
{
    case STUDIO = 'studio';
    case ONE_BEDROOM = 'one_bedroom';
    case TWO_BEDROOM = 'two_bedroom';
    case THREE_BEDROOM = 'three_bedroom';
    case PENTHOUSE = 'penthouse';
    case ROOM = 'room';
    case COMMERCIAL_OFFICE = 'commercial_office';
    case RETAIL_SHOP = 'retail_shop';
    case WAREHOUSE = 'warehouse';
    case INDUSTRIAL = 'industrial';
    case STORE = 'store';
    case PARKING_BAY = 'parking_bay';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::STUDIO => __('Studio'),
            self::ONE_BEDROOM => __('One Bedroom'),
            self::TWO_BEDROOM => __('Two Bedroom'),
            self::THREE_BEDROOM => __('Three Bedroom'),
            self::PENTHOUSE => __('Penthouse'),
            self::ROOM => __('Room'),
            self::COMMERCIAL_OFFICE => __('Commercial Office'),
            self::RETAIL_SHOP => __('Retail Shop'),
            self::WAREHOUSE => __('Warehouse'),
            self::INDUSTRIAL => __('Industrial'),
            self::STORE => __('Store'),
            self::PARKING_BAY => __('Parking Bay'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::STUDIO, self::ONE_BEDROOM, self::TWO_BEDROOM, self::THREE_BEDROOM, self::PENTHOUSE, self::ROOM => 'success',
            self::COMMERCIAL_OFFICE, self::RETAIL_SHOP, self::WAREHOUSE, self::INDUSTRIAL => 'info',
            self::STORE, self::PARKING_BAY => 'gray',
        };
    }

    /**
     * The classification a type normally falls under — a sensible default
     * when creating a unit, not a hard rule (an office building's parking bay
     * is still commercial, so this is overridable on the unit itself).
     */
    public function defaultClassification(): PropertyClassification
    {
        return match ($this) {
            self::STUDIO, self::ONE_BEDROOM, self::TWO_BEDROOM, self::THREE_BEDROOM, self::PENTHOUSE, self::ROOM => PropertyClassification::RESIDENTIAL,
            self::COMMERCIAL_OFFICE, self::RETAIL_SHOP => PropertyClassification::COMMERCIAL,
            self::WAREHOUSE, self::INDUSTRIAL => PropertyClassification::INDUSTRIAL,
            self::STORE, self::PARKING_BAY => PropertyClassification::RESIDENTIAL,
        };
    }
}
