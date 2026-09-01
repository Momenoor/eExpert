<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MatterDifficulty: string implements HasColor, HasIcon, HasLabel
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EASY => __('Easy'),
            self::MEDIUM => __('Medium'),
            self::HARD => __('Hard'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EASY => 'success',
            self::MEDIUM => 'warning',
            self::HARD => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::EASY, self::MEDIUM, self::HARD => Heroicon::InformationCircle,
        };
    }
}
