<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MatterStatus: string implements HasColor, HasLabel
{
    case IN_PROGRESS = 'In Progress';
    case INITIALED = 'Initial Report';
    case FINALIZED = 'Final Report';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::IN_PROGRESS => __('In Progress'),
            self::INITIALED => __('Initial Report'),
            self::FINALIZED => __('Final Report'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IN_PROGRESS => 'info',
            self::INITIALED => 'warning',
            self::FINALIZED => 'success',
        };
    }
}
