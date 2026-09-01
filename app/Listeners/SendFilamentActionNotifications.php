<?php

namespace App\Listeners;

use App\Events\FilamentActionEvent;
use App\Models\Allocation;
use App\Models\Fee;
use App\Models\Matter;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SendFilamentActionNotifications
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(FilamentActionEvent $event): void
    {
        $action = $event->action;
        $record = $event->record;
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $matter = $this->resolveMatter($record);
        if (! $matter) {
            return;
        }

        $recipients = $this->getRecipients($event, $matter);

        if ($recipients->isEmpty()) {
            return;
        }

        $title = $this->getNotificationTitle($event, $matter);
        $body = $this->getNotificationBody($event, $user);

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-information-circle')
            ->sendToDatabase($recipients);
    }

    protected function resolveMatter($record): ?Matter
    {
        if ($record instanceof Matter) {
            return $record;
        }

        if ($record instanceof Fee || $record instanceof Allocation) {
            return $record->matter;
        }

        // Try to find matter_id property if it's another type of model
        if (isset($record->matter_id)) {
            return Matter::find($record->matter_id);
        }

        return null;
    }

    protected function getRecipients(FilamentActionEvent $event, Matter $matter): Collection
    {
        $recipients = collect();

        // 1. All users with role admin or superadmin
        $admins = User::role(['admin', 'super-admin', 'super_admin'])->get();
        $recipients = $recipients->merge($admins);

        // 2. Notify role accountant only if action related to fee or allocation
        if ($this->isFeeRelated($event)) {
            $accountants = User::role('accountant')->get();
            $recipients = $recipients->merge($accountants);
        }

        // 3. Notify user who is a related party of case assistant
        $assistants = $this->getCaseAssistants($matter);
        $recipients = $recipients->merge($assistants);

        // Remove duplicates and the user who performed the action
        return $recipients->unique('id')->reject(fn ($u) => $u->id === auth()->id());
    }

    protected function isFeeRelated(FilamentActionEvent $event): bool
    {
        $record = $event->record;
        $actionName = Str::lower($event->action->getName());

        if ($record instanceof Fee || $record instanceof Allocation) {
            return true;
        }

        if (Str::contains($actionName, ['fee', 'allocation'])) {
            return true;
        }

        return false;
    }

    protected function getCaseAssistants(Matter $matter): Collection
    {
        // Case assistants are parties linked to the matter with type 'assistant' or 'certified' (experts)
        // We need to find the Users associated with these Parties.

        $assistantPartyIds = $matter->matterParties()
            ->whereIn('type', ['assistant', 'certified'])
            ->pluck('party_id');

        if ($assistantPartyIds->isEmpty()) {
            return collect();
        }

        // Based on Party model having user_id
        return User::whereIn('id', function ($query) use ($assistantPartyIds) {
            $query->select('user_id')
                ->from('parties')
                ->whereIn('id', $assistantPartyIds)
                ->whereNotNull('user_id');
        })->get();
    }

    protected function getNotificationTitle(FilamentActionEvent $event, Matter $matter): string
    {
        $actionLabel = $event->action->getLabel() ?: $event->action->getName();
        $matterNumber = $matter->number ?: $matter->id;

        return "Action on Matter #{$matterNumber}: {$actionLabel}";
    }

    protected function getNotificationBody(FilamentActionEvent $event, User $user): string
    {
        $actionLabel = $event->action->getLabel() ?: $event->action->getName();
        $userName = $user->display_name ?: $user->name;
        $recordName = $event->record ? (method_exists($event->record, 'getRecordTitle') ? $event->record->getRecordTitle() : ($event->record->number ?? $event->record->id)) : 'Unknown';

        return "User {$userName} performed '{$actionLabel}' on {$recordName}.";
    }
}
