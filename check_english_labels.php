<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

app()->setLocale('ar');

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Contracts\Console\Kernel;

$englishFound = [];

// 1. Pages
foreach (FilamentShield::getPages() as $p) {
    foreach ($p['permissions'] as $permKey => $permLabel) {
        if (preg_match('/[a-zA-Z]{3,}/', $permLabel)) {
            $englishFound[] = "Page perm [{$permKey}] has English label: '{$permLabel}'";
        }
    }
}

// 2. Widgets
foreach (FilamentShield::getWidgets() as $w) {
    foreach ($w['permissions'] as $permKey => $permLabel) {
        if (preg_match('/[a-zA-Z]{3,}/', $permLabel)) {
            $englishFound[] = "Widget perm [{$permKey}] has English label: '{$permLabel}'";
        }
    }
}

// 3. Custom permissions
foreach (FilamentShield::getCustomPermissions(true) as $k => $v) {
    if (preg_match('/[a-zA-Z]{3,}/', $v)) {
        $englishFound[] = "Custom perm [{$k}] has English label: '{$v}'";
    }
}

// 4. Resources
foreach (FilamentShield::getResources() as $resFqcn => $res) {
    $resLabel = FilamentShield::getLocalizedResourceLabel($resFqcn);
    if (preg_match('/[a-zA-Z]{3,}/', $resLabel)) {
        $englishFound[] = "Resource [{$resFqcn}] has English label: '{$resLabel}'";
    }
    foreach ($res['permissions'] as $action => $p) {
        if (preg_match('/[a-zA-Z]{3,}/', $p['label'])) {
            $englishFound[] = "Resource perm [{$p['key']}] has English label: '{$p['label']}'";
        }
    }
}

echo "=== UNTRANSLATED / ENGLISH LABELS IN SHIELD ===\n";
if (empty($englishFound)) {
    echo "NONE! All permissions, resources, pages, widgets, and custom permissions have 100% Arabic labels!\n";
} else {
    foreach ($englishFound as $ef) {
        echo '  '.$ef."\n";
    }
}
