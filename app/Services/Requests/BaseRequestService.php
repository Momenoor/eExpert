<?php

namespace App\Services\Requests;

use App\Enums\MatterDifficulty;
use App\Enums\MatterStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Mail\NewRequestNotificationMail;
use App\Mail\RequestActionNotificationMail;
use App\Models\MatterRequest;
use App\Models\User;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

abstract class BaseRequestService
{
    public function __construct(protected MatterRequest $request)
    {
    }

    abstract public function approve(array $data = [], $component = null): void;

    abstract public function reject(array $data = [], $component = null): void;

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

    protected function refresh($component): void
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

    protected function notify(string $title, string $body, mixed $recipients): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('view')
                    ->url(route('filament.admin.resources.matter-requests.view', $this->request))
                    ->markAsRead()
            ])
            ->sendToDatabase($recipients);
    }

    private function onActionNotify(string $title, string $body): void
    {
        $statusLabel = $this->request->status->getLabel();

        $assistants = $this->request->matter->assistantsOnly
            ->map(fn($mp) => $mp->party?->user)
            ->filter();
        $assistants->each(fn($user) => $this->notify($title, $body, $user));

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
                __("A new :type request has been created, for matter #:number / :year", [
                    'type' => $this->request->type->getLabel(),
                    'number' => $this->request->matter->number,
                    'year' => $this->request->matter->year
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
            \Log::error("Failed to send notification: " . $e->getMessage());
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

    public static function getDefaultCreateAction(): \Closure
    {
        return function (array $data, $record, $component) {
            $extra = null;
            $comment = $data['comment'];

            if ($data['type'] === RequestType::CHANGE_DIFFICULTY && !empty($data['new_difficulty'])) {

                $difficulty = $data['new_difficulty'];
                $comment = __('New Difficulty MatterRequest') . ': ' . $difficulty->getLabel() . '. ' . $comment;
                $extra = ['new_difficulty' => $difficulty->value];
            } elseif ($data['type'] === RequestType::REVIEW_REPORT) {
                $extra = ['review_report' => $record->id];
                $record->increment('review_count');
            }

            // Create request record
            $request = $record->requests()->create([
                'request_by' => auth()->id(),
                'type' => $data['type'],
                'status' => 'pending',
                'comment' => $comment,
                'extra' => $extra,
            ]);
            foreach ($data['attachments'] ?? [] as $item) {
                $path = $item['path'];
                $request->attachments()->create([
                    'name' => 'request-attachment-' . $request->id . '-' . basename($path),
                    'path' => $path,
                    'size' => Storage::disk('public')->size($path),
                    'extension' => pathinfo($path, PATHINFO_EXTENSION),
                    'type' => 'matter-request',
                    'matter_id' => $record->id,
                    'matter_request_id' => $request->id,
                    'user_id' => auth()->id(),
                ]);
            }
            RequestServiceFactory::make($request)->onCreateNotify();
            RequestServiceFactory::make($request)->refresh($component);
        };
    }

    /**
     * @param $attachments
     * @return void
     */
    protected function storeFile($attachments): void
    {
        if ($attachments) {
            foreach ($attachments as $item) {
                $path = $item['path'];
                $this->request->attachments()->create([
                    'name' => 'request-attachment-' . $this->request->id . '-' . basename($path),
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
