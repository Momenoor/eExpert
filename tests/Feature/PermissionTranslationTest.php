<?php

declare(strict_types=1);

namespace Tests\Feature;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\AllPermissionsSeeder;
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

    public function test_all_shield_pages_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (FilamentShield::getPages() as $page) {
            foreach ($page['permissions'] as $permKey => $permLabel) {
                $locKey = Utils::toLocalizationKey($permKey);
                $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;
                $hasShield = Lang::has($fullKey, 'ar');
                $hasDirect = Lang::has($permKey, 'ar') || filled($permLabel);

                if (! $hasShield && ! $hasDirect) {
                    $missing[] = "Page perm [{$permKey}] => key [{$locKey}]";
                }
            }
        }

        $this->assertSame([], $missing, "Untranslated pages:\n".implode("\n", $missing));
    }

    public function test_all_shield_widgets_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (FilamentShield::getWidgets() as $widget) {
            foreach ($widget['permissions'] as $permKey => $permLabel) {
                $locKey = Utils::toLocalizationKey($permKey);
                $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;
                $hasShield = Lang::has($fullKey, 'ar');
                $hasDirect = Lang::has($permKey, 'ar') || filled($permLabel);

                if (! $hasShield && ! $hasDirect) {
                    $missing[] = "Widget perm [{$permKey}] => key [{$locKey}]";
                }
            }
        }

        $this->assertSame([], $missing, "Untranslated widgets:\n".implode("\n", $missing));
    }

    public function test_all_shield_custom_permissions_are_translated_in_arabic(): void
    {
        $missing = [];

        foreach (FilamentShield::getCustomPermissions(true) as $key => $label) {
            $locKey = Utils::toLocalizationKey($key);
            $fullKey = 'filament-shield::filament-shield.resource_permission_prefixes_labels.'.$locKey;

            if (! Lang::has($fullKey, 'ar') && ! Lang::has($key, 'ar')) {
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
