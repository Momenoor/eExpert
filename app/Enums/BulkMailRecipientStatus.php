<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BulkMailRecipientStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => __('bulk_mail.recipient_status.pending'),
            self::Sent => __('bulk_mail.recipient_status.sent'),
            self::Failed => __('bulk_mail.recipient_status.failed'),
            self::Skipped => __('bulk_mail.recipient_status.skipped'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'warning',
        };
    }
}
