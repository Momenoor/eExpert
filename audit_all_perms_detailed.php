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

echo "=== 1. MISSING RESOURCE AFFIXES ===\n";
$missingAffixes = [];
foreach (FilamentShield::getResources() as $resFqcn => $res) {
    if (isset($res['permissions'])) {
        foreach ($res['permissions'] as $action => $p) {
            $affix = $action;
            $locKey = Utils::toLocalizationKey($affix);
            $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey);
            if (! $hasTrans) {
                $missingAffixes[$locKey] = ['affix' => $affix, 'resource' => $resFqcn, 'label' => FilamentShield::getAffixLabel($affix)];
                echo "  Affix [{$affix}] -> key: [{$locKey}] (Resource: {$resFqcn})\n";
            }
        }
    }
}

echo "\n=== 2. MISSING PAGES ===\n";
foreach (FilamentShield::getPages() as $page) {
    $perm = $page['permission'] ?? '';
    $locKey = Utils::toLocalizationKey($perm);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey);
    if (! $hasTrans) {
        $label = FilamentShield::getEntityPermissionLabel($page['pageFqcn'] ?? $page['class'] ?? '', $perm);
        echo "  Page [{$perm}] -> key: [{$locKey}] (Label: {$label})\n";
    }
}

echo "\n=== 3. MISSING WIDGETS ===\n";
foreach (FilamentShield::getWidgets() as $widget) {
    $perm = $widget['permission'] ?? '';
    $locKey = Utils::toLocalizationKey($perm);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey);
    if (! $hasTrans) {
        $label = FilamentShield::getEntityPermissionLabel($widget['widgetFqcn'] ?? $widget['class'] ?? '', $perm);
        echo "  Widget [{$perm}] -> key: [{$locKey}] (Label: {$label})\n";
    }
}

echo "\n=== 4. MISSING CUSTOM PERMISSIONS ===\n";
foreach (FilamentShield::getCustomPermissions(true) as $key => $label) {
    $locKey = Utils::toLocalizationKey($key);
    $hasTrans = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey);
    if (! $hasTrans) {
        echo "  Custom [{$key}] -> key: [{$locKey}] (Label: {$label})\n";
    }
}

echo "\n=== 5. ALL DB PERMISSIONS NOT TRANSLATED IN SHIELD OR AR.JSON ===\n";
$untranslatedDb = [];
foreach (Permission::pluck('name') as $pName) {
    $locKey = Utils::toLocalizationKey($pName);
    $hasDirectShield = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey);
    $hasJson = Lang::has($pName) || Lang::has($locKey);

    $parts = explode(':', $pName);
    $prefixLocKey = Utils::toLocalizationKey($parts[0]);
    $hasPrefixShield = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$prefixLocKey);

    if (! $hasDirectShield && ! $hasPrefixShield && ! $hasJson) {
        $untranslatedDb[] = $pName;
        echo "  DB Perm: [{$pName}] (locKey: {$locKey}, prefixLocKey: {$prefixLocKey})\n";
    }
}
echo 'Total Untranslated DB Permissions: '.count($untranslatedDb)."\n";
