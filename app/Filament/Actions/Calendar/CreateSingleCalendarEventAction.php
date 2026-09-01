<?php

namespace App\Filament\Actions\Calendar;

use App\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Models\CalendarEvent;
use App\Services\OutlookCalendarService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateSingleCalendarEventAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createSingleEvent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Create Single Event'))
            ->modalHeading(__('Create Single Calendar Event'))
            ->icon('heroicon-o-calendar')
            ->schema(CalendarEventForm::getFormSchema())
            ->action(function (array $data, OutlookCalendarService $outlookService) {
                $data['type'] = 'single';
                $data['created_by'] = Auth::id();
                $event = CalendarEvent::create($data);

                if (isset($data['update_next_session_date']) && $event->matter_id) {
                    $event->matter->update(['next_session_date' => $data['start_datetime']]);
                }

                if ($data['sync_to_outlook']) {
                    try {
                        $outlookEvent = $outlookService->createEvent([
                            'title' => $data['title'],
                            'description' => $data['description'],
                            'start_datetime' => $data['start_datetime'],
                            'end_datetime' => $data['end_datetime'],
                            'location' => $data['location'],
                            'is_teams_meeting' => $data['is_teams_meeting'] ?? false,
                        ]);

                        $event->update([
                            'outlook_event_id' => $outlookEvent['id'],
                            'synced_to_outlook' => true,
                            'online_meeting_url' => $outlookEvent['onlineMeeting']['joinUrl'] ?? $outlookEvent['webLink'] ?? null,
                        ]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Outlook Sync Failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }

                Notification::make()
                    ->title(__('Event Created Successfully'))
                    ->success()
                    ->send();
            });
    }
}
