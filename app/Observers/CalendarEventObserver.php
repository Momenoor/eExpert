<?php

namespace App\Observers;

use App\Models\CalendarEvent;
use App\Services\OutlookCalendarService;
use Illuminate\Support\Facades\Log;

class CalendarEventObserver
{
    /**
     * Remove the event from Outlook when it is deleted locally.
     *
     * Guarded on two counts: an event that was never synced has a null
     * outlook_event_id and would hit a non-nullable string parameter, and a
     * Graph outage must not make local events undeletable — the local delete is
     * already committed by this point, so a remote failure is logged, not thrown.
     */
    public function deleted(CalendarEvent $event): void
    {
        if (blank($event->outlook_event_id)) {
            return;
        }

        try {
            app(OutlookCalendarService::class)->deleteEvent($event->outlook_event_id);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete Outlook event for calendar event '.$event->id.': '.$e->getMessage());
        }
    }
}
