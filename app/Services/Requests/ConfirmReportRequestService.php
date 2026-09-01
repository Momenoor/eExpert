<?php

namespace App\Services\Requests;

use Filament\Forms\Components\Toggle;

class ConfirmReportRequestService extends BaseRequestService
{
    public static function requiresAttachmentsOnCreate(): bool
    {
        return true;
    }

    public static function approvalFormFields(): array
    {
        return [
            Toggle::make('has_substantive_changes')
                ->label(__('Has Substantive Changes'))
                ->default(false),
        ];
    }

    public static function rejectionRequiresAttachments(): bool
    {
        return true;
    }

    public function approve(array $data = [], $component = null): void
    {
        $this->markApproved($data);

        // Apply the proposed received date to the matter
        $this->request->matter->update([
            'final_report_memo_date' => now(),
        ]);

        $this->onApproveNotify();
        $this->refresh($component);
    }

    public function reject(array $data = [], $component = null): void
    {
        $this->markRejected($data);

        // Notify the assistant of rejection with reason
        $this->onRejectNotify();

        $this->refresh($component);
    }
}
