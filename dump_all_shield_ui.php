<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

app()->setLocale('ar');

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Contracts\Console\Kernel;

echo "=== PAGES PERMISSIONS ===\n";
foreach (FilamentShield::getPages() as $p) {
    foreach ($p['permissions'] as $permKey => $permLabel) {
        echo "  [{$permKey}] => '{$permLabel}'\n";
    }
}

echo "\n=== WIDGETS PERMISSIONS ===\n";
foreach (FilamentShield::getWidgets() as $w) {
    foreach ($w['permissions'] as $permKey => $permLabel) {
        echo "  [{$permKey}] => '{$permLabel}'\n";
    }
}

echo "\n=== CUSTOM PERMISSIONS ===\n";
foreach (FilamentShield::getCustomPermissions(true) as $k => $v) {
    echo "  [{$k}] => '{$v}'\n";
}

echo "\n=== RESOURCES PERMISSIONS ===\n";
foreach (FilamentShield::getResources() as $resFqcn => $res) {
    echo "Resource: {$resFqcn} (Label: ".FilamentShield::getLocalizedResourceLabel($resFqcn).")\n";
    foreach ($res['permissions'] as $action => $p) {
        echo "  {$action}: [{$p['key']}] => '{$p['label']}'\n";
    }
}
