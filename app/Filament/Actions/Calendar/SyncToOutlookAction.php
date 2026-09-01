<?php

namespace App\Filament\Actions\Calendar;

use App\Models\CalendarEvent;
use App\Models\Matter;
use App\Services\OutlookCalendarService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class SyncToOutlookAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'syncToOutlook';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Sync to Outlook'))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn ($record) => ($record instanceof CalendarEvent && ! $record->synced_to_outlook) || ($record instanceof Matter && $record->calendarEvents()->where('synced_to_outlook', false)->exists()))
            ->action(function ($record, OutlookCalendarService $outlookService) {
                if ($record instanceof Matter) {
                    $record = $record->calendarEvents()->where('synced_to_outlook', false)->latest()->first();
                }

                if (! $record instanceof CalendarEvent) {
                    return;
                }
                try {
                    $outlookEvent = $outlookService->createEvent([
                        'title' => $record->title,
                        'description' => $record->description,
                        'start_datetime' => $record->start_datetime,
                        'end_datetime' => $record->end_datetime,
                        'location' => $record->location,
                        'is_teams_meeting' => $record->is_teams_meeting,
                    ]);

                    $record->update([
                        'outlook_event_id' => $outlookEvent['id'],
                        'synced_to_outlook' => true,
                        'online_meeting_url' => $outlookEvent['onlineMeeting']['joinUrl'] ?? $outlookEvent['webLink'] ?? null,
                    ]);

                    Notification::make()
                        ->title(__('Synced to Outlook Successfully'))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title(__('Outlook Sync Failed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
