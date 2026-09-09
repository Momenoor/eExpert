<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

app()->setLocale('ar');

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Permission;

echo "=== CHECKING SHIELD LABELS IN ARABIC ===\n";

// 1. Resources & their affixes
foreach (FilamentShield::getResources() as $res) {
    if (isset($res['permissions'])) {
        foreach ($res['permissions'] as $p) {
            $affix = $p['name'] ?? $p['key'];
            $label = FilamentShield::getLocalizedLabel($affix);
            $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.Utils::toLocalizationKey($affix));
            if (! $hasTrans) {
                echo '  [MISSING AFFIX]: '.$affix.' -> '.Utils::toLocalizationKey($affix).' (Resolved: '.$label.")\n";
            }
        }
    }
}

// 2. Pages
foreach (FilamentShield::getPages() as $page) {
    $perm = $page['permission'] ?? '';
    $label = FilamentShield::getEntityPermissionLabel($page['class'], $perm);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.Utils::toLocalizationKey($perm));
    if (! $hasTrans) {
        echo '  [MISSING PAGE]: '.$perm.' -> '.Utils::toLocalizationKey($perm).' (Resolved: '.$label.")\n";
    }
}

// 3. Widgets
foreach (FilamentShield::getWidgets() as $widget) {
    $perm = $widget['permission'] ?? '';
    $label = FilamentShield::getEntityPermissionLabel($widget['class'], $perm);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.Utils::toLocalizationKey($perm));
    if (! $hasTrans) {
        echo '  [MISSING WIDGET]: '.$perm.' -> '.Utils::toLocalizationKey($perm).' (Resolved: '.$label.")\n";
    }
}

// 4. Custom permissions
foreach (FilamentShield::getCustomPermissions() as $cp) {
    $key = is_array($cp) ? ($cp['key'] ?? $cp['name']) : $cp;
    $label = FilamentShield::getCustomPermissionLabel($key);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.Utils::toLocalizationKey($key));
    if (! $hasTrans) {
        echo '  [MISSING CUSTOM]: '.$key.' -> '.Utils::toLocalizationKey($key).' (Resolved: '.$label.")\n";
    }
}

// 5. All permissions in DB
echo "\n=== ALL DB PERMISSIONS LOCALIZATION CHECK ===\n";
foreach (Permission::pluck('name') as $pName) {
    $key = Utils::toLocalizationKey($pName);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$key);
    $parts = explode(':', $pName);
    $prefixTrans = false;
    if (count($parts) == 2) {
        $prefixKey = Utils::toLocalizationKey($parts[0]);
        $prefixTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$prefixKey);
    }

    $jsonTrans = Lang::has($pName);

    echo "DB Perm: [{$pName}] | Key: [{$key}] | In Shield: ".($hasTrans ? 'YES' : 'NO').' | Prefix In Shield: '.($prefixTrans ? 'YES' : 'NO').' | In ar.json: '.($jsonTrans ? 'YES' : 'NO')."\n";
}
