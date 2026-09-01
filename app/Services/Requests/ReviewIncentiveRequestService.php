<?php

namespace App\Services\Requests;

class ReviewIncentiveRequestService extends BaseRequestService
{
    public function approve(array $data = [], $component = null): void
    {
        $this->markApproved($data);

        // TODO: trigger incentive recalculation for this matter

        $this->onApproveNotify();
        $this->refresh($component);
    }

    public function reject(array $data = [], $component = null): void
    {
        $this->markRejected($data);
        $this->onRejectNotify();
        $this->refresh($component);
    }
}
