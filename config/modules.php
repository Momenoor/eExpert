<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled Modules
    |--------------------------------------------------------------------------
    |
    | Which panels/sub-modules this deployment has installed. Set by the
    | installer wizard's Modules step, written into `.env` as MODULE_*
    | flags. Every flag defaults to true so an existing deployment with no
    | flags set at all keeps behaving exactly as it always has.
    |
    | "mms" is Legal Core plus whichever of its own sub-modules are on —
    | there is no separate "legal core" flag because it is MMS's always-on
    | baseline, not something a deployment can turn off on its own.
    |
    */

    'pms' => env('MODULE_PMS_ENABLED', true),

    'mms' => env('MODULE_MMS_ENABLED', true),

    'mms_payroll' => env('MODULE_MMS_PAYROLL_ENABLED', true),

    'mms_communications' => env('MODULE_MMS_COMMUNICATIONS_ENABLED', true),

    'mms_calendar' => env('MODULE_MMS_CALENDAR_ENABLED', true),

];
