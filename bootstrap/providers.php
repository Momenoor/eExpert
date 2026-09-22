<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\MmsPanelProvider;
use App\Providers\Filament\PmsPanelProvider;

// Read directly from env() rather than config('modules.*') — this file runs
// before the config repository exists, let alone `modules.php` within it.
// Both default to true so a deployment with no MODULE_* flags in its .env
// (every existing one, until the installer's Modules step sets them) keeps
// registering both panels exactly as it always has.
return array_filter([
    AppServiceProvider::class,
    filter_var(env('MODULE_MMS_ENABLED', true), FILTER_VALIDATE_BOOLEAN) ? MmsPanelProvider::class : null,
    filter_var(env('MODULE_PMS_ENABLED', true), FILTER_VALIDATE_BOOLEAN) ? PmsPanelProvider::class : null,
]);
