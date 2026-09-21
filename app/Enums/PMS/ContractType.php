<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasLabel;

/**
 * What a lease is for. It is chosen on the lease, but only from the types
 * that fit the classification of the units it covers.
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
     * The contract types that fit a unit's classification: the four
     * residential ones for a residential unit, the commercial ones for a
     * commercial or industrial one, and both for mixed use.
     *
     * @return list<self>
     */
    public static function forClassification(PropertyClassification $classification): array
    {
        $residential = [self::FAMILY, self::BACHELORS, self::LABOUR, self::EMPLOYEES];
        $commercial = [self::SHOP, self::WAREHOUSE, self::STORE, self::OFFICE, self::LAND, self::PARKING];

        return match ($classification) {
            PropertyClassification::RESIDENTIAL => $residential,
            PropertyClassification::COMMERCIAL, PropertyClassification::INDUSTRIAL => $commercial,
            PropertyClassification::MIXED_USE => [...$residential, ...$commercial],
        };
    }

    /**
     * A commercial unit type names its own contract type, so it is offered
     * as the starting choice; a dwelling could be let to a family, bachelors,
     * labour or employees, so there is no default for it.
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
