<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Server
    |--------------------------------------------------------------------------
    |
    | Where this installation activates and periodically re-verifies its
    | license key. Set by the installer wizard's License step, but always
    | overridable in `.env` for a self-hosted license server.
    |
    */

    'server_url' => env('LICENSE_SERVER_URL', 'https://license.mie-app.example'),

    /*
    |--------------------------------------------------------------------------
    | Installation ID
    |--------------------------------------------------------------------------
    |
    | This installation's own stable identity — generated once (by the
    | installer's License step) and written to `.env` as INSTALLATION_ID.
    | Sent as the `fingerprint` on every activate/verify call, so the
    | license server can tell this install apart from any other sharing
    | the same key, up to the license's own `max_activations`.
    |
    */

    'installation_id' => env('INSTALLATION_ID'),

    'app_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Offline Grace Period
    |--------------------------------------------------------------------------
    |
    | How long this app keeps running without a successful check-in
    | before `EnsureLicenseIsValid` locks it down. Covers ordinary
    | connectivity gaps to the license server without punishing an
    | otherwise-valid install for a temporary outage.
    |
    */

    'grace_days' => env('LICENSE_GRACE_DAYS', 7),

];
