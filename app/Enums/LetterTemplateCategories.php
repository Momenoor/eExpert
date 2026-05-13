<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum LetterTemplateCategories: string implements HasLabel,HasColor
{
    case LETTER = 'letter';
    case COURT_NOTICES = 'court_notices';
    case MEETING_NOTICES = 'meeting_notices';
    case APPOINTMENTS = 'appointments';
    case REPORTS = 'reports';
    case OTHERS = 'others';

    public function getColor(): string|array|null
    {
       return match ($this) {
            self::LETTER => 'success',
            self::COURT_NOTICES => 'warning',
            self::MEETING_NOTICES => 'info',
            self::APPOINTMENTS => 'primary',
            self::REPORTS => 'danger',
            self::OTHERS => 'gray',
        };
    }

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::LETTER => __('Letter'),
            self::COURT_NOTICES => __('Court Notices'),
            self::MEETING_NOTICES => __('Meeting Notices'),
            self::APPOINTMENTS => __('Appointments'),
            self::REPORTS => __('Reports'),
            self::OTHERS => __('Others'),
        };
    }
}
