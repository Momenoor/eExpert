<?php

namespace App\Services\Requests;

use App\Models\Matter;
use Filament\Forms\Components\Toggle;

class ReviewReportRequestService extends BaseRequestService
{
    public static function requiresAttachmentsOnCreate(): bool
    {
        return true;
    }

    public static function prepareForCreation(array $data, Matter $matter): array
    {
        return [
            'comment' => $data['comment'] ?? null,
            'extra' => ['review_report' => $matter->id],
        ];
    }

    public function afterCreated(): void
    {
        $this->request->matter->increment('review_count');
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

        $this->request->matter->update([
            'initial_report_at' => now(),
            'has_substantive_changes' => $data['has_substantive_changes'] ?? false,
        ]);

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
