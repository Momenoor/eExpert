<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PropertyType: string implements HasColor, HasLabel
{
    case BUILDING = 'building';
    case VILLA = 'villa';
    case TOWER = 'tower';
    case LAND = 'land';
    case SHOP = 'shop';
    case WAREHOUSE = 'warehouse';
    case COMPOUND = 'compound';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BUILDING => __('Building'),
            self::VILLA => __('Villa'),
            self::TOWER => __('Tower'),
            self::LAND => __('Land'),
            self::SHOP => __('Shop'),
            self::WAREHOUSE => __('Warehouse'),
            self::COMPOUND => __('Compound'),
            self::OTHER => __('Other'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BUILDING, self::TOWER => 'info',
            self::VILLA, self::COMPOUND => 'success',
            self::LAND => 'warning',
            self::SHOP, self::WAREHOUSE => 'gray',
            self::OTHER => 'gray',
        };
    }
}
