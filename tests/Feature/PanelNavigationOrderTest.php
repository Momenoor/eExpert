<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelNavigationOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_mms_panel_navigation_groups_order_en(): void
    {
        app()->setLocale('en');
        $panel = Filament::getPanel('mms');
        Filament::setCurrentPanel($panel);

        $groups = collect($panel->getNavigationGroups())
            ->map(fn ($g) => $g instanceof NavigationGroup ? $g->getLabel() : $g)
            ->values()
            ->all();

        $this->assertSame([
            'Communication',
            'Financial',
            'Human Resources',
            'Settings',
            'Filament Shield',
        ], $groups);
    }

    public function test_mms_panel_navigation_groups_order_ar(): void
    {
        app()->setLocale('ar');
        $panel = Filament::getPanel('mms');
        Filament::setCurrentPanel($panel);

        $groups = collect($panel->getNavigationGroups())
            ->map(fn ($g) => $g instanceof NavigationGroup ? $g->getLabel() : $g)
            ->values()
            ->all();

        $this->assertSame([
            __('Communication'),
            __('Financial'),
            __('Human Resources'),
            __('Settings'),
            __('filament-shield::filament-shield.nav.group'),
        ], $groups);
    }

    public function test_pms_panel_navigation_groups_order_en(): void
    {
        app()->setLocale('en');
        $panel = Filament::getPanel('pms');
        Filament::setCurrentPanel($panel);

        $groups = collect($panel->getNavigationGroups())
            ->map(fn ($g) => $g instanceof NavigationGroup ? $g->getLabel() : $g)
            ->values()
            ->all();

        $this->assertSame([
            'Properties',
            'Leasing',
            'Ownership',
            'Settings',
            'Filament Shield',
        ], $groups);
    }

    public function test_pms_panel_navigation_groups_order_ar(): void
    {
        app()->setLocale('ar');
        $panel = Filament::getPanel('pms');
        Filament::setCurrentPanel($panel);

        $groups = collect($panel->getNavigationGroups())
            ->map(fn ($g) => $g instanceof NavigationGroup ? $g->getLabel() : $g)
            ->values()
            ->all();

        $this->assertSame([
            __('Properties'),
            __('Leasing'),
            __('Ownership'),
            __('Settings'),
            __('filament-shield::filament-shield.nav.group'),
        ], $groups);
    }

    public function test_mms_panel_rendered_navigation_order(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);
        app()->setLocale('en');

        $panel = Filament::getPanel('mms');
        Filament::setCurrentPanel($panel);

        $navGroups = collect(Filament::getNavigation())
            ->map(fn (NavigationGroup $group) => $group->getLabel())
            ->filter()
            ->values()
            ->all();

        $settingsIndex = array_search('Settings', $navGroups);
        $shieldIndex = array_search('Filament Shield', $navGroups);

        $this->assertNotFalse($settingsIndex, 'Settings group should be present');
        $this->assertNotFalse($shieldIndex, 'Filament Shield group should be present');
        $this->assertGreaterThanOrEqual(count($navGroups) - 2, $settingsIndex, 'Settings should be near the bottom');
        $this->assertGreaterThanOrEqual(count($navGroups) - 2, $shieldIndex, 'Shield should be near the bottom');
    }

    public function test_pms_panel_rendered_navigation_order(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);
        app()->setLocale('en');

        $panel = Filament::getPanel('pms');
        Filament::setCurrentPanel($panel);

        $navGroups = collect(Filament::getNavigation())
            ->map(fn (NavigationGroup $group) => $group->getLabel())
            ->filter()
            ->values()
            ->all();

        $settingsIndex = array_search('Settings', $navGroups);
        $shieldIndex = array_search('Filament Shield', $navGroups);

        $this->assertNotFalse($settingsIndex, 'Settings group should be present');
        $this->assertNotFalse($shieldIndex, 'Filament Shield group should be present');
        $this->assertGreaterThanOrEqual(count($navGroups) - 2, $settingsIndex, 'Settings should be near the bottom');
        $this->assertGreaterThanOrEqual(count($navGroups) - 2, $shieldIndex, 'Shield should be near the bottom');
    }
}
