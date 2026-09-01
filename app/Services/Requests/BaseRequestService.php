<?php

namespace App\Services\Requests;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Mail\NewRequestNotificationMail;
use App\Mail\RequestActionNotificationMail;
use App\Models\Matter;
use App\Models\MatterRequest;
use App\Models\User;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

abstract class BaseRequestService
{
    public function __construct(protected MatterRequest $request) {}

    abstract public function approve(array $data = [], $component = null): void;

    abstract public function reject(array $data = [], $component = null): void;

    /**
     * Extra Filament form components shown in the "Add Request" modal once this type is selected.
     *
     * @return array<Component>
     */
    public static function createFormFields(): array
    {
        return [];
    }

    public static function requiresAttachmentsOnCreate(): bool
    {
        return false;
    }

    /**
     * Build the comment/extra payload to store on the new MatterRequest row.
     *
     * @return array{comment: ?string, extra: ?array}
     */
    public static function prepareForCreation(array $data, Matter $matter): array
    {
        return [
            'comment' => $data['comment'] ?? null,
            'extra' => null,
        ];
    }

    /**
     * Hook run right after the MatterRequest row (and its attachments) have been created.
     */
    public function afterCreated(): void
    {
        //
    }

    /**
     * Extra Filament form components shown in the "Approve" modal for this type.
     *
     * @return array<Component>
     */
    public static function approvalFormFields(): array
    {
        return [];
    }

    public static function rejectionRequiresAttachments(): bool
    {
        return false;
    }

    public function canBeApproved(User $user): bool
    {
        return $this->canBeActedOnBy($user, 'ApproveRequest:Matter');
    }

    public function canBeRejected(User $user): bool
    {
        return $this->canBeActedOnBy($user, 'RejectRequest:Matter');
    }

    private function canBeActedOnBy(User $user, string $matterPermission): bool
    {
        if (! ($user->can('EditRequest:MatterRequest') || $user->can($matterPermission) || $user->hasAnyRole('super-admin', 'super_admin'))) {
            return false;
        }

        return $this->request->status === RequestStatus::DISPUTED
            || ($this->request->status === RequestStatus::PENDING && $this->request->type !== RequestType::CHANGE_DISTRIBUTED_DATE)
            || ($this->request->status === RequestStatus::PENDING && auth()->id() === $this->request->request_by);
    }

    protected function markApproved(array $data): void
    {
        $this->request->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approved_comment' => $data['approved_comment'] ?? null,
        ]);

        $this->storeFile($data['attachments']);
    }

    protected function markRejected(array $data): void
    {

        $this->request->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approved_comment' => $data['approved_comment'],
        ]);
        $this->storeFile($data['attachments']);
    }

    public function refresh($component): void
    {
        $this->request->refresh();
        $this->request->unsetRelation('matter');
        if ($component) {
            $livewire = $component->getLivewire();
            $livewire->dispatch('$refresh');

            // Refresh the parent matter record if available
            if (method_exists($livewire, 'getRecord') && $livewire->getRecord()) {
                $livewire->getRecord()->refresh();
                $livewire->getRecord()->unsetRelation('requests');
            }
        }
    }

    private function notify(string $title, string $body, mixed $recipients): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('view')
                    ->url(route('filament.admin.resources.matter-requests.view', $this->request))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    private function onActionNotify(string $title, string $body): void
    {
        $statusLabel = $this->request->status->getLabel();

        $assistants = $this->request->matter->assistantsOnly
            ->map(fn ($mp) => $mp->party?->user)
            ->filter();
        $assistants->each(fn ($user) => $this->notify($title, $body, $user));

        if ($assistants->isNotEmpty()) {
            $emails = $assistants->pluck('email')->filter();
            if ($emails->isNotEmpty()) {
                Mail::to($emails)->send(new RequestActionNotificationMail(
                    $this->request->matter,
                    $this->request,
                    $statusLabel
                ));
            }
        }
    }

    public function onCreateNotify(): void
    {
        try {
            $users = User::role(['admin', 'super-admin', 'super_admin'])->get();
            $this->notify(
                __('Request Created'),
                __('A new :type request has been created, for matter #:number / :year', [
                    'type' => $this->request->type->getLabel(),
                    'number' => $this->request->matter->number,
                    'year' => $this->request->matter->year,
                ]),
                $users
            );

            $whatsappUsers = $users->filter(function ($user) {
                return $user->notify_by_whatsapp;
            });
            $whatsappUsers->map(function ($user) {
                WhatsAppService::notifyNewRequest($user, $this->request);
            });
            $emails = $users->pluck('email');
            Mail::to($emails)
                ->send(new NewRequestNotificationMail(
                    $this->request->matter,
                    $this->request
                )
                );

        } catch (\Exception $e) {
            \Log::error('Failed to send notification: '.$e->getMessage());
        }

    }

    protected function onApproveNotify(): void
    {
        $this->onActionNotify(
            __('Request Approved'),
            __('Your request has been approved.')
        );
    }

    protected function onRejectNotify(): void
    {
        $reason = $this->request->approved_comment;
        $this->onActionNotify(
            __('Request Rejected'),
            __('Your request has been rejected. Reason: :reason', ['reason' => $reason])
        );
    }

    protected function storeFile($attachments): void
    {
        if ($attachments) {
            foreach ($attachments as $item) {
                $path = $item['path'];
                $this->request->attachments()->create([
                    'name' => 'request-attachment-'.$this->request->id.'-'.basename($path),
                    'path' => $path,
                    'size' => Storage::disk('public')->size($path),
                    'extension' => pathinfo($path, PATHINFO_EXTENSION),
                    'type' => 'matter-request',
                    'matter_id' => $this->request->matter_id,
                    'matter_request_id' => $this->request->id,
                    'user_id' => auth()->id(),
                ]);
            }
        }
    }
}
