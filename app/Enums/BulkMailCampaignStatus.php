<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BulkMailCampaignStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => __('bulk_mail.status.draft'),
            self::Active => __('bulk_mail.status.active'),
            self::Paused => __('bulk_mail.status.paused'),
            self::Completed => __('bulk_mail.status.completed'),
            self::Cancelled => __('bulk_mail.status.cancelled'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Paused => 'warning',
            self::Completed => 'info',
            self::Cancelled => 'danger',
        };
    }
}
