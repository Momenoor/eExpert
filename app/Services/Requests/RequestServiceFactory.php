<?php

namespace App\Services\Requests;

use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Model;

class RequestServiceFactory
{
    /**
     * @return class-string<BaseRequestService>
     */
    public static function classFor(RequestType $type): string
    {
        return match ($type) {
            RequestType::CHANGE_DIFFICULTY => ChangeDifficultyRequestService::class,
            RequestType::CHANGE_DISTRIBUTED_DATE => ChangeDistributedAtRequestService::class,
            RequestType::CONFIRM_OFFICE_WORK => ConfirmOfficeWorkRequestService::class,
            RequestType::REVIEW_INCENTIVE => ReviewIncentiveRequestService::class,
            RequestType::REVIEW_REPORT => ReviewReportRequestService::class,
            RequestType::CONFIRM_REPORT => ConfirmReportRequestService::class,
            default => throw new \InvalidArgumentException("Unsupported request type: {$type->value}"),
        };
    }

    public static function make(Model $request): BaseRequestService
    {
        return new (self::classFor($request->type))($request);
    }
}
