<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasLabel;

/**
 * What a unit is let for. It is defined once, on the unit (its "rental
 * type"), and every lease on that unit takes its contract type from it —
 * nobody picks it on the lease itself.
 */
enum ContractType: string implements HasLabel
{
    // Residential.
    case FAMILY = 'family';
    case BACHELORS = 'bachelors';
    case LABOUR = 'labour';
    case EMPLOYEES = 'employees';

    // Commercial.
    case SHOP = 'shop';
    case WAREHOUSE = 'warehouse';
    case STORE = 'store';
    case OFFICE = 'office';
    case LAND = 'land';
    case PARKING = 'parking';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FAMILY => __('Family'),
            self::BACHELORS => __('Bachelors'),
            self::LABOUR => __('Labour Accommodation'),
            self::EMPLOYEES => __('Employees'),
            self::SHOP => __('Shop'),
            self::WAREHOUSE => __('Warehouse'),
            self::STORE => __('Store'),
            self::OFFICE => __('Office'),
            self::LAND => __('Land'),
            self::PARKING => __('Parking Bay'),
        };
    }

    public function isResidential(): bool
    {
        return in_array($this, [self::FAMILY, self::BACHELORS, self::LABOUR, self::EMPLOYEES], true);
    }

    /**
     * The rental types a unit of this type can be let as: the four
     * residential ones for a dwelling, the commercial ones for anything else.
     *
     * @return list<self>
     */
    public static function forUnitType(UnitType $type): array
    {
        return in_array($type, [UnitType::STUDIO, UnitType::APARTMENT, UnitType::PENTHOUSE, UnitType::ROOM], true)
            ? [self::FAMILY, self::BACHELORS, self::LABOUR, self::EMPLOYEES]
            : [self::SHOP, self::WAREHOUSE, self::STORE, self::OFFICE, self::LAND, self::PARKING];
    }

    /**
     * Commercial unit types name their own rental type; a dwelling could be
     * let to a family, bachelors, labour or employees, so it stays a choice.
     */
    public static function defaultForUnitType(UnitType $type): ?self
    {
        return match ($type) {
            UnitType::OFFICE => self::OFFICE,
            UnitType::SHOP => self::SHOP,
            UnitType::WAREHOUSE => self::WAREHOUSE,
            UnitType::STORE => self::STORE,
            UnitType::LAND => self::LAND,
            UnitType::PARKING_BAY => self::PARKING,
            default => null,
        };
    }
}
