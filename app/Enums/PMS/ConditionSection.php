<?php

namespace App\Enums\PMS;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which block of a printed contract a `ConditionTemplateItem` belongs to.
 * SPECIAL is deliberately never populated from a template — it stays a
 * blank box on every printed contract, filled in by hand per lease.
 */
enum ConditionSection: string implements HasColor, HasLabel
{
    case SPECIAL = 'special';
    case GENERAL = 'general';
    case RIGHTS = 'rights';
    case ATTACHMENTS = 'attachments';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SPECIAL => __('Special Conditions'),
            self::GENERAL => __('General Conditions'),
            self::RIGHTS => __('Know Your Rights'),
            self::ATTACHMENTS => __('Attachments'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SPECIAL => 'warning',
            self::GENERAL => 'info',
            self::RIGHTS => 'success',
            self::ATTACHMENTS => 'gray',
        };
    }
}
