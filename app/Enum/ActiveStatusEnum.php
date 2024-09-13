<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ActiveStatusEnum: int implements HasColor, HasIcon, HasLabel
{
    case Active = 1;
    case Inactive = 0;

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-m-check',
            self::Inactive => 'heroicon-m-x-mark',
        };
    }

    public function getLabel(): ?string
    {
        return $this->name;
    }
}
