<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UnitType: string implements HasColor, HasLabel
{
    case STUDIO = 'studio';
    case APARTMENT = 'apartment';
    case PENTHOUSE = 'penthouse';
    case ROOM = 'room';
    case OFFICE = 'office';
    case SHOP = 'shop';
    case WAREHOUSE = 'warehouse';
    case STORE = 'store';
    case PARKING_BAY = 'parking_bay';
    case LAND = 'land';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::STUDIO => __('Studio'),
            self::APARTMENT => __('Apartment'),
            self::PENTHOUSE => __('Penthouse'),
            self::ROOM => __('Room'),
            self::OFFICE => __('Office'),
            self::SHOP => __('Shop'),
            self::WAREHOUSE => __('Warehouse'),
            self::STORE => __('Store'),
            self::PARKING_BAY => __('Parking Bay'),
            self::LAND => __('Land'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::STUDIO, self::APARTMENT, self::PENTHOUSE, self::ROOM => 'success',
            self::OFFICE, self::SHOP, self::WAREHOUSE, self::LAND => 'info',
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
            self::STUDIO, self::APARTMENT, self::PENTHOUSE, self::ROOM => PropertyClassification::RESIDENTIAL,
            self::OFFICE, self::SHOP, self::LAND => PropertyClassification::COMMERCIAL,
            self::WAREHOUSE => PropertyClassification::INDUSTRIAL,
            self::STORE, self::PARKING_BAY => PropertyClassification::RESIDENTIAL,
        };
    }
}
