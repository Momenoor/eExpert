<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Services\License\LicenseClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The lockout page `EnsureLicenseIsValid` sends an installed-but-
 * unlicensed app to, and the one place (besides the installer's own
 * License step) a key can be (re-)entered — a replacement key after a
 * renewal, or the first key if the installer's own step was skipped.
 */
class LicenseController extends Controller
{
    public function show(): View
    {
        return view('license.show', [
            'license' => License::current(),
        ]);
    }

    public function activate(Request $request, LicenseClient $client): RedirectResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string'],
        ]);

        $fingerprint = config('license.installation_id') ?: License::current()?->fingerprint;
        $result = $client->activate($data['license_key'], (string) $fingerprint, config('app.url'));

        if (! $result['valid']) {
            return back()->withErrors(['license_key' => __('That key could not be activated: :reason', ['reason' => $result['reason']])]);
        }

        License::updateOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'key' => $data['license_key'],
                'status' => 'active',
                'plan' => $result['plan'],
                'expires_at' => $result['expires_at'],
                'last_checked_at' => now(),
                'last_valid_at' => now(),
            ],
        );

        return redirect('/');
    }
}
