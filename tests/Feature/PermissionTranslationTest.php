<?php

declare(strict_types=1);

namespace Tests\Feature;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\AllPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ar');
        $this->seed(AllPermissionsSeeder::class);
    }

    public function test_all_shield_resource_affixes_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (FilamentShield::getResources() as $resFqcn => $res) {
            if (isset($res['permissions'])) {
                foreach ($res['permissions'] as $action => $p) {
                    $locKey = Utils::toLocalizationKey($action);
                    $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;

                    if (! Lang::has($fullKey, 'ar')) {
                        $missing[] = "Resource [{$resFqcn}] affix [{$action}] => key [{$locKey}]";
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Untranslated resource affixes:\n".implode("\n", $missing));
    }

    /**
     * Shield's own entity discovery (`getPages()`/`getWidgets()`) is scoped
     * to whichever panel is "current" when called — the `mms` panel by
     * default, since it's the app's default panel (see `MmsPanelProvider`).
     * Checking only that default silently skipped every `pms` panel page
     * and widget, which is exactly how `View:PMSOverviewWidget`'s missing
     * translation slipped past this test — so both panels are checked here.
     */
    public function test_all_shield_pages_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (['mms', 'pms'] as $panelId) {
            Filament::setCurrentPanel(Filament::getPanel($panelId));

            foreach (FilamentShield::getPages() as $page) {
                foreach ($page['permissions'] as $permKey => $permLabel) {
                    $locKey = Utils::toLocalizationKey($permKey);
                    $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;

                    if (! Lang::has($fullKey, 'ar')) {
                        $missing[] = "[{$panelId}] Page perm [{$permKey}] => key [{$locKey}]";
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Untranslated pages:\n".implode("\n", $missing));
    }

    public function test_all_shield_widgets_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (['mms', 'pms'] as $panelId) {
            Filament::setCurrentPanel(Filament::getPanel($panelId));

            foreach (FilamentShield::getWidgets() as $widget) {
                foreach ($widget['permissions'] as $permKey => $permLabel) {
                    $locKey = Utils::toLocalizationKey($permKey);
                    $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;

                    if (! Lang::has($fullKey, 'ar')) {
                        $missing[] = "[{$panelId}] Widget perm [{$permKey}] => key [{$locKey}]";
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Untranslated widgets:\n".implode("\n", $missing));
    }

    /**
     * Matches exactly what `HasLabelResolver::getCustomPermissionLabel()`
     * checks at render time — no fallback to a raw-key `lang/ar.json` entry,
     * because Shield's own UI has no such fallback either. A JSON entry can
     * make this test pass while the live Role editor still shows the
     * untranslated `Str::headline()` fallback, which is exactly how
     * `View:EosgClosingVoucher`/`Generate:EosgClosingVoucher` went
     * unnoticed despite this test passing.
     */
    public function test_all_shield_custom_permissions_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (FilamentShield::getCustomPermissions(true) as $key => $label) {
            $locKey = Utils::toLocalizationKey($key);
            $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;

            if (! Lang::has($fullKey, 'ar')) {
                $missing[] = "Custom perm [{$key}] => key [{$locKey}]";
            }
        }

        $this->assertSame([], $missing, "Untranslated custom permissions:\n".implode("\n", $missing));
    }

    public function test_all_database_permissions_have_arabic_translations(): void
    {
        $missing = [];

        foreach (Permission::pluck('name') as $pName) {
            $locKey = Utils::toLocalizationKey($pName);
            $hasDirectShield = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey, 'ar');
            $hasJson = Lang::has($pName, 'ar') || Lang::has($locKey, 'ar');

            $parts = explode(':', $pName);
            $prefixLocKey = Utils::toLocalizationKey($parts[0]);
            $hasPrefixShield = Lang::has('filament-shield::filament-shield.resource_permission_prefixes_labels.'.$prefixLocKey, 'ar');

            if (! $hasDirectShield && ! $hasPrefixShield && ! $hasJson) {
                $missing[] = "DB Permission [{$pName}]";
            }
        }

        $this->assertSame([], $missing, "Untranslated database permissions:\n".implode("\n", $missing));
    }
}
