<?php

namespace App\Services\Requests;

use App\Models\Matter;

class ConfirmOfficeWorkRequestService extends BaseRequestService
{
    public static function prepareForCreation(array $data, Matter $matter): array
    {
        return [
            'comment' => $data['comment'] ?? __('Kindly confirm that the report has been completed on our end.'),
            'extra' => null,
        ];
    }

    public function approve(array $data = [], $component = null): void
    {
        $this->markApproved($data);
        $this->request->matter->update(['is_office_work' => true]);
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
