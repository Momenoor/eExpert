<?php

namespace App\Services\Requests;

use App\Enums\RequestStatus;
use App\Models\Matter;
use App\Models\User;
use Filament\Forms\Components\DatePicker;

class ChangeDistributedAtRequestService extends BaseRequestService
{
    public static function createFormFields(): array
    {
        return [
            DatePicker::make('proposed_distributed_at')
                ->label(__('Proposed Assistant Assigning Date'))
                ->required(),
        ];
    }

    public static function prepareForCreation(array $data, Matter $matter): array
    {
        return [
            'comment' => $data['comment'] ?? null,
            'extra' => ! empty($data['proposed_distributed_at'])
                ? ['proposed_distributed_at' => $data['proposed_distributed_at']]
                : null,
        ];
    }

    public function canBeApproved(User $user): bool
    {
        return $this->request->status === RequestStatus::PENDING
            && (auth()->id() === $this->request->request_by || $user->hasAnyRole('super-admin', 'super_admin'));
    }

    public function canBeRejected(User $user): bool
    {
        return $this->canBeApproved($user);
    }

    public function approve(array $data = [], $component = null): void
    {
        $this->markApproved($data);

        // Apply the proposed received date to the matter
        $proposedDate = $this->request->extra['proposed_distributed_at'] ?? null;
        if ($proposedDate) {
            $this->request->matter->update([
                'distributed_at' => $proposedDate,
            ]);
        }

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
