<?php

namespace App\Services\License;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only place this app talks to the license server — the installer's
 * License step calls `activate()` once; `App\Console\Commands\VerifyLicense`
 * (scheduled) calls `verify()` on every check-in. Both return the same
 * shape regardless of success/failure/network error, so callers never
 * need a try/catch of their own.
 */
class LicenseClient
{
    /**
     * @return array{valid: bool, reason: string|null, expires_at: string|null, plan: string|null}
     */
    public function activate(string $licenseKey, string $fingerprint, string $domain): array
    {
        return $this->call('activate', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'domain' => $domain,
            'app_version' => config('license.app_version'),
        ]);
    }

    /**
     * @return array{valid: bool, reason: string|null, expires_at: string|null, plan: string|null}
     */
    public function verify(string $licenseKey, string $fingerprint): array
    {
        return $this->call('verify', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * @param  array<string, string|null>  $payload
     * @return array{valid: bool, reason: string|null, expires_at: string|null, plan: string|null}
     */
    private function call(string $endpoint, array $payload): array
    {
        try {
            $response = Http::timeout(10)
                ->baseUrl(rtrim((string) config('license.server_url'), '/').'/api/v1/license')
                ->post($endpoint, $payload);

            $body = $response->json();

            return [
                'valid' => (bool) ($body['valid'] ?? false),
                'reason' => $body['reason'] ?? ($response->successful() ? null : 'server_error'),
                'expires_at' => $body['expires_at'] ?? null,
                'plan' => $body['plan'] ?? null,
            ];
        } catch (Throwable) {
            return [
                'valid' => false,
                'reason' => 'network_error',
                'expires_at' => null,
                'plan' => null,
            ];
        }
    }
}
