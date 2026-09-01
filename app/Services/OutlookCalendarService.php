<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OutlookCalendarService
{
    public function getUserEmail(): string
    {
        return config('services.outlook.user_email');
    }

    /**
     * The single source of truth for "what timezone does this office run on".
     *
     * Every dateTime sent to or read from Graph goes through this — hardcoding
     * the zone name at each call site is how 'Asia/Muscat' and 'Asia/Mascut'
     * (a typo — not a real IANA identifier) ended up side by side in this same
     * class, silently defaulting Graph to UTC on whichever path used the typo.
     */
    public function appTimezone(): string
    {
        return config('app.timezone');
    }

    public function getAccessToken(): string
    {
        return Cache::remember('outlook_access_token', 50 * 60, function () {
            $config = config('services.outlook');
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/token", [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to get Outlook access token: '.$response->body());
            }

            return $response->json('access_token');
        });
    }

    public function createEvent(array $eventData): array
    {
        $tz = $this->appTimezone();

        $payload = [
            'subject' => $eventData['title'],
            'body' => [
                'contentType' => 'text',
                'content' => $eventData['description'] ?? '',
            ],
            'start' => [
                'dateTime' => Carbon::parse($eventData['start_datetime'], $tz)->toIso8601String(),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => isset($eventData['end_datetime'])
                    ? Carbon::parse($eventData['end_datetime'], $tz)->toIso8601String()
                    : Carbon::parse($eventData['start_datetime'], $tz)->addHour()->toIso8601String(),
                'timeZone' => $tz,
            ],
            'location' => [
                'displayName' => $eventData['location'] ?? '',
            ],
        ];

        if (! empty($eventData['is_teams_meeting'])) {
            $payload['isOnlineMeeting'] = true;
            $payload['onlineMeetingProvider'] = 'teamsForBusiness';
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$this->getUserEmail()}/events", $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to create Outlook event: '.$response->body());
        }

        return $response->json();
    }

    public function updateEvent(string $outlookEventId, array $eventData): array
    {
        $tz = $this->appTimezone();

        $response = Http::withToken($this->getAccessToken())
            ->patch("https://graph.microsoft.com/v1.0/users/{$this->getUserEmail()}/events/{$outlookEventId}", [
                'subject' => $eventData['title'],
                'body' => [
                    'contentType' => 'text',
                    'content' => $eventData['description'] ?? '',
                ],
                'start' => [
                    // Both the missing tz argument here and the 'Asia/Mascut'
                    // typo below used to make Graph default this to UTC — four
                    // hours off Asia/Muscat — for every field this method
                    // touches, independently of createEvent()'s own (correct)
                    // handling of the same data.
                    'dateTime' => Carbon::parse($eventData['start_datetime'], $tz)->toIso8601String(),
                    'timeZone' => $tz,
                ],
                'end' => [
                    'dateTime' => isset($eventData['end_datetime'])
                        ? Carbon::parse($eventData['end_datetime'], $tz)->toIso8601String()
                        : Carbon::parse($eventData['start_datetime'], $tz)->addHour()->toIso8601String(),
                    'timeZone' => $tz,
                ],
                'location' => [
                    'displayName' => $eventData['location'] ?? '',
                ],
                'isOnlineMeeting' => $eventData['is_teams_meeting'] ?? false,
                'onlineMeeting' => [
                    'joinUrl' => $eventData['online_meeting_url'] ?? '',
                ],
                'isAllDay' => $eventData['is_all_day'] ?? false,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to update Outlook event: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @throws ConnectionException
     */
    public function deleteEvent(string $eventId): void
    {
        $response = Http::withToken($this->getAccessToken())
            ->delete(
                "https://graph.microsoft.com/v1.0/users/{$this->getUserEmail()}/events/{$eventId}"
            );

        if ($response->failed() && $response->status() !== 404) {
            throw new \RuntimeException('Outlook event deletion failed: '.$response->json('error.message'));
        }
    }

    /**
     * @throws ConnectionException
     */
    public function importEvents(?Carbon $from = null): array
    {
        $url = "https://graph.microsoft.com/v1.0/users/{$this->getUserEmail()}/events";

        $params = [
            '$select' => 'subject,body,start,end,location,id,isOnlineMeeting,onlineMeeting,onlineMeetingUrl,isAllDay',
            '$top' => 50,
        ];
        if ($from) {
            $params['$filter'] = "start/dateTime ge '".$from->toIso8601String()."'";
        }

        $response = Http::withToken($this->getAccessToken())->get($url, $params);
        if ($response->failed()) {
            throw new \RuntimeException('Failed to import Outlook events: '.$response->body());
        }

        return $response->json('value');
    }

    /**
     * Turn one side of a Graph event (its 'start' or 'end' object) into a
     * Carbon instant in the app's own timezone.
     *
     * Graph's dateTimeTimeZone shape is `{dateTime, timeZone}`, where dateTime
     * is a WALL-CLOCK string with no offset — it means nothing without the
     * paired timeZone. The import action used to hand the bare dateTime string
     * to Carbon::parse() with no timezone argument at all, which made PHP
     * interpret it in the app's own default zone. Since Graph's default
     * response zone is UTC (no `Prefer: outlook.timezone` header is sent here),
     * that silently read a UTC wall-clock time as if it were already Muscat
     * wall-clock time — four hours early on every imported event, regardless
     * of daylight saving in either zone (neither observes it).
     *
     * @param  array{dateTime: string, timeZone: string}  $side
     */
    public function graphDateTimeToApp(array $side): Carbon
    {
        // PHP's DateTimeZone accepts "UTC" natively, which is what Graph
        // reports here by default. It does NOT accept a Windows timezone name
        // (e.g. "Arabian Standard Time") — Graph only sends one of those if the
        // request explicitly asked for it via a Prefer header, which this
        // integration does not send, so this fallback is a safety net rather
        // than the expected path.
        $zone = $side['timeZone'] ?? 'UTC';

        try {
            return Carbon::parse($side['dateTime'], $zone)->setTimezone($this->appTimezone());
        } catch (\Exception) {
            return Carbon::parse($side['dateTime'], 'UTC')->setTimezone($this->appTimezone());
        }
    }
}
