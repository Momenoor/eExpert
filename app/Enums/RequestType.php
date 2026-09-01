<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RequestType: string implements HasColor, HasLabel
{
    case CHANGE_DIFFICULTY = 'change_difficulty';
    case CHANGE_DISTRIBUTED_DATE = 'change_distributed_date';
    case CONFIRM_OFFICE_WORK = 'confirm_office_work';
    case REVIEW_INCENTIVE = 'review_incentive';
    case REVIEW_REPORT = 'review_report';
    case CONFIRM_REPORT = 'confirm_report';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CHANGE_DIFFICULTY => __('Change Difficulty'),
            self::CHANGE_DISTRIBUTED_DATE => __('Change Distributed Date'),
            self::CONFIRM_OFFICE_WORK => __('Confirm Office Work'),
            self::REVIEW_INCENTIVE => __('Review Incentive'),
            self::REVIEW_REPORT => __('Review Report'),
            self::CONFIRM_REPORT => __('Confirm Report'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CHANGE_DIFFICULTY => 'info',
            self::REVIEW_INCENTIVE => 'warning',
            self::REVIEW_REPORT,self::CONFIRM_REPORT => 'success',
            self::CHANGE_DISTRIBUTED_DATE => 'primary',
            default => 'gray',
        };
    }
}
