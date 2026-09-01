<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum LetterStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case RECEIVED = 'received';
    case ARCHIVED = 'archived';
    case SCHEDULED = 'scheduled';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
    case DELIVERED = 'delivered';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::SENT => __('Sent'),
            self::RECEIVED => __('Received'),
            self::ARCHIVED => __('Archived'),
            self::SCHEDULED => __('Scheduled'),
            self::CANCELLED => __('Cancelled'),
            self::FAILED => __('Failed'),
            self::DELIVERED => __('Delivered'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SENT => 'success',
            self::RECEIVED => 'info',
            self::ARCHIVED => 'warning',
            self::SCHEDULED => 'primary',
            self::CANCELLED, self::FAILED => 'danger',
        };
    }

    public function getIcon(): string|null|\BackedEnum|Htmlable
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document-text',
            self::SENT => 'heroicon-o-envelope',
            self::RECEIVED => 'heroicon-o-envelope-open',
            self::ARCHIVED => 'heroicon-o-archive-box',
            self::SCHEDULED => 'heroicon-o-calendar',
            self::CANCELLED, self::FAILED => 'heroicon-o-x-circle',
            self::DELIVERED => 'heroicon-o-check-circle',
        };
    }
}
