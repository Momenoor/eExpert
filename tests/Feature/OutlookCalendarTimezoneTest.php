<?php

namespace Tests\Feature;

use App\Filament\Resources\CalendarEvents\Pages\ListCalendarEvents;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\OutlookCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Graph's dateTimeTimeZone shape is `{dateTime, timeZone}` — the dateTime
 * string is a wall-clock value that means nothing on its own. Two independent
 * bugs used to lose that pairing:
 *
 *  - Importing read only `dateTime` and parsed it with no timezone at all, so
 *    PHP fell back to the app's own default (Asia/Muscat). Since Graph's
 *    default response zone is UTC, a UTC wall-clock time got read as if it
 *    were already Muscat time — four hours early on every imported event.
 *  - updateEvent() sent 'Asia/Mascut' (not a real IANA identifier — a typo)
 *    as the outgoing timeZone, and parsed the incoming Carbon value with no
 *    timezone argument either, unlike createEvent()'s correct handling of the
 *    same data.
 *
 * Both are pinned here against the app's real configured zone rather than a
 * hardcoded 'Asia/Muscat' literal, so the tests keep meaning if that ever
 * changes.
 */
class OutlookCalendarTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private string $appTz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appTz = config('app.timezone');

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
        ]);
    }

    public function test_graph_date_time_to_app_converts_a_utc_instant_to_the_app_timezone(): void
    {
        $service = app(OutlookCalendarService::class);

        // A real-world instance of the bug: an event at 14:00 local time is
        // stored by Outlook as 10:00 UTC (Muscat is UTC+4, no DST).
        $result = $service->graphDateTimeToApp([
            'dateTime' => '2026-09-02T10:00:00.0000000',
            'timeZone' => 'UTC',
        ]);

        $this->assertSame('2026-09-02 14:00:00', $result->format('Y-m-d H:i:s'));
        $this->assertSame($this->appTz, $result->timezone->getName());
    }

    public function test_graph_date_time_to_app_defaults_to_utc_when_the_zone_is_missing(): void
    {
        $service = app(OutlookCalendarService::class);

        $result = $service->graphDateTimeToApp(['dateTime' => '2026-09-02T10:00:00.0000000']);

        $this->assertSame('2026-09-02 14:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_create_event_sends_the_apps_timezone_with_no_typo(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
            'graph.microsoft.com/v1.0/users/*/events' => Http::response(['id' => 'outlook-1']),
        ]);

        app(OutlookCalendarService::class)->createEvent([
            'title' => 'Hearing',
            'start_datetime' => '2026-09-02 14:00:00',
            'end_datetime' => '2026-09-02 15:00:00',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'graph.microsoft.com')) {
                return true;
            }

            return $request['start']['timeZone'] === $this->appTz
                && $request['end']['timeZone'] === $this->appTz
                && str_starts_with($request['start']['dateTime'], '2026-09-02T14:00:00')
                && ! str_contains($request['start']['timeZone'], 'Mascut');
        });
    }

    public function test_update_event_sends_the_apps_timezone_with_no_typo(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
            'graph.microsoft.com/v1.0/users/*/events/*' => Http::response(['id' => 'outlook-1']),
        ]);

        app(OutlookCalendarService::class)->updateEvent('outlook-1', [
            'title' => 'Hearing (rescheduled)',
            'start_datetime' => '2026-09-02 16:00:00',
            'end_datetime' => '2026-09-02 17:00:00',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'graph.microsoft.com')) {
                return true;
            }

            // The regression this pins: 'Asia/Mascut' is not a real timezone,
            // so Graph silently defaulted these fields to UTC.
            $this->assertNotEquals('Asia/Mascut', $request['start']['timeZone']);
            $this->assertNotEquals('Asia/Mascut', $request['end']['timeZone']);

            return $request['start']['timeZone'] === $this->appTz
                && $request['end']['timeZone'] === $this->appTz
                && str_starts_with($request['start']['dateTime'], '2026-09-02T16:00:00');
        });
    }

    public function test_importing_a_utc_event_lands_on_the_correct_local_time(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'test-token']),
            'graph.microsoft.com/v1.0/users/*/events*' => Http::response([
                'value' => [
                    [
                        'id' => 'outlook-imported-1',
                        'subject' => 'Hearing 123/2026',
                        'body' => ['content' => ''],
                        'start' => ['dateTime' => '2026-09-02T10:00:00.0000000', 'timeZone' => 'UTC'],
                        'end' => ['dateTime' => '2026-09-02T11:00:00.0000000', 'timeZone' => 'UTC'],
                        'location' => ['displayName' => ''],
                        'isOnlineMeeting' => false,
                        'isAllDay' => false,
                    ],
                ],
            ]),
        ]);

        Livewire::test(ListCalendarEvents::class)
            ->callTableAction('import', record: null, data: ['from_date' => '2026-09-01']);

        $event = CalendarEvent::where('outlook_event_id', 'outlook-imported-1')->firstOrFail();

        // 10:00 UTC is 14:00 in Asia/Muscat (UTC+4, no DST) — the bug stored
        // this as 10:00, four hours early.
        $this->assertSame('2026-09-02 14:00:00', $event->start_datetime->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 15:00:00', $event->end_datetime->format('Y-m-d H:i:s'));
        $this->assertSame($this->appTz, $event->start_datetime->timezone->getName());
    }
}
