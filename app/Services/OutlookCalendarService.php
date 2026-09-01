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
        $payload = [
            'subject' => $eventData['title'],
            'body' => [
                'contentType' => 'text',
                'content' => $eventData['description'] ?? '',
            ],
            'start' => [
                'dateTime' => Carbon::parse($eventData['start_datetime'], 'Asia/Muscat')->toIso8601String(),
                'timeZone' => 'Asia/Muscat',
            ],
            'end' => [
                'dateTime' => isset($eventData['end_datetime'])
                    ? Carbon::parse($eventData['end_datetime'], 'Asia/Muscat')->toIso8601String()
                    : Carbon::parse($eventData['start_datetime'], 'Asia/Muscat')->addHour()->toIso8601String(),
                'timeZone' => 'Asia/Muscat',
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
        $response = Http::withToken($this->getAccessToken())
            ->patch("https://graph.microsoft.com/v1.0/users/{$this->getUserEmail()}/events/{$outlookEventId}", [
                'subject' => $eventData['title'],
                'body' => [
                    'contentType' => 'text',
                    'content' => $eventData['description'] ?? '',
                ],
                'start' => [
                    'dateTime' => Carbon::parse($eventData['start_datetime'])->toIso8601String(),
                    'timeZone' => 'Asia/Mascut',
                ],
                'end' => [
                    'dateTime' => isset($eventData['end_datetime'])
                        ? Carbon::parse($eventData['end_datetime'])->toIso8601String()
                        : Carbon::parse($eventData['start_datetime'])->addHour()->toIso8601String(),
                    'timeZone' => 'Asia/Mascut',
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
}
