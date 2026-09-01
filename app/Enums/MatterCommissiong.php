<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MatterCommissiong: string implements HasColor, HasIcon, HasLabel
{
    case INDIVIDUAL = 'individual';
    case COMMITTEE = 'committee';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INDIVIDUAL => __('Individual'),
            self::COMMITTEE => __('Committee'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INDIVIDUAL => 'success',
            self::COMMITTEE => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::INDIVIDUAL => Heroicon::Users,
            self::COMMITTEE => Heroicon::UserGroup,
        };
    }
}
