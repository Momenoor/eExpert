<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation Lock File
    |--------------------------------------------------------------------------
    |
    | The presence of this file is the fast-path signal that the installer has
    | already run. Configurable — rather than hardcoded to storage_path() —
    | purely so the test suite can point it at a throwaway path instead of ever
    | touching the real file that gates this deployment's own installed state.
    |
    */

    'lock_file' => storage_path('installed'),

];
