<?php

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Contracts\Console\Kernel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$shieldPerms = FilamentShield::getEntitiesPermissions();
$allDbPermissions = Permission::orderBy('name')->pluck('name')->toArray();

$extraInDb = array_values(array_diff($allDbPermissions, $shieldPerms));
echo 'Extra In DB to delete: '.count($extraInDb)."\n";
if (! empty($extraInDb)) {
    Permission::whereIn('name', $extraInDb)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    echo 'Deleted '.count($extraInDb)." orphaned permissions.\n";
}

$remaining = Permission::count();
echo 'Remaining DB Permissions: '.$remaining.' (Expected: '.count($shieldPerms).")\n";
