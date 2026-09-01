<?php

namespace App\Filament\Actions\Calendar;

use App\Models\CalendarEvent;
use App\Models\Matter;
use App\Services\OutlookCalendarService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ImportFromOutlookAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'importFromOutlook';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Import from Outlook'))
            ->modalHeading(__('Import Calendar Events from Outlook'))
            ->icon('heroicon-o-cloud-arrow-down')
            ->schema([
                DatePicker::make('from_date')
                    ->default(now())
                    ->label(__('Import events from date')),
            ])
            ->action(function (array $data, OutlookCalendarService $outlookService) {
                $fromDate = $data['from_date'] ? Carbon::parse($data['from_date']) : null;

                try {
                    $outlookEvents = $outlookService->importEvents($fromDate);
                    $importedCount = 0;

                    foreach ($outlookEvents as $event) {
                        $exists = CalendarEvent::where('outlook_event_id', $event['id'])->exists();
                        if ($exists) {
                            continue;
                        }
                        $matterIds = $this->findMatterIdsFromText($event['subject']);
                        $CalendarEvent = CalendarEvent::create([
                            'title' => $event['subject'],
                            'start_datetime' => Carbon::parse($event['start']['dateTime'])->timezone('Asia/Dubai'),
                            'end_datetime' => Carbon::parse($event['end']['dateTime'])->timezone('Asia/Dubai'),
                            'outlook_event_id' => $event['id'],
                            'imported_from_outlook' => true,
                            'synced_to_outlook' => true,
                            'update_next_session_date' => false,
                            'location' => $event['location']['displayName'] ?? '',
                            'description' => $event['body']['content'] ?? '',
                            'is_teams_meeting' => $event['isOnlineMeeting'] ?? false,
                            'online_meeting_url' => $event['onlineMeeting']['joinUrl'] ?? null,
                            'is_all_day' => $event['isAllDay'] ?? false,
                            'type' => count($matterIds) < 2 ? 'single' : 'bulk',
                            'created_by' => Auth::id(),
                        ]);
                        if ($matterIds) {
                            $CalendarEvent->matters()->syncWithoutDetaching($matterIds);
                            if (count($matterIds) == 1) {
                                $CalendarEvent->update(['matter_id' => $matterIds[0]]);
                            }
                        }
                        $importedCount++;
                    }

                    Notification::make()
                        ->title(__('Import Completed'))
                        ->body(__('Imported :count new events from Outlook', ['count' => $importedCount]))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title(__('Import Failed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Extract Matter IDs from a string (Subject or Description).
     * Handles: "639/2025", "2025/639", "639 of 2025", and Arabic "639 لسنة 2025"
     */
    private function findMatterIdsFromText(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        // 1. Normalize Numbers: Convert Arabic/Hindi digits (٠١٢٣) to Western (0123)
        $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latinNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedText = str_replace($arabicNumbers, $latinNumbers, $text);

        $matterIds = [];

        /**
         * 2. Define Regex Patterns
         * Pattern A: [Number] followed by [/ or "of" or "لسنة"] followed by [4-digit Year]
         * Pattern B: [4-digit Year] followed by [/] followed by [Number]
         */
        $patternA = '/(\d+)\s*(?:\/|of|لسنة)\s*(\d{4})/i';
        $patternB = '/(\d{4})\/(\d+)/';

        // Execute Pattern A (Number then Year)
        if (preg_match_all($patternA, $normalizedText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = $match[1];
                $year = $match[2];

                $id = Matter::where('year', $year)
                    ->where('number', $number)
                    ->value('id'); // value() is faster than first()

                if ($id) {
                    $matterIds[] = $id;
                }
            }
        }

        // Execute Pattern B (Year then Number)
        if (preg_match_all($patternB, $normalizedText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $year = $match[1];
                $number = $match[2];

                $id = Matter::where('year', $year)
                    ->where('number', $number)
                    ->value('id');

                if ($id) {
                    $matterIds[] = $id;
                }
            }
        }

        // 3. Return unique IDs only
        return array_unique($matterIds);
    }
}
