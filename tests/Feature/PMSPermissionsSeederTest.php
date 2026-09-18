<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PMSPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PMSPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_crud_permissions_for_every_pms_resource(): void
    {
        $this->seed(PMSPermissionsSeeder::class);

        foreach (['Property', 'Lease', 'Tenant', 'OwnerProfile', 'OwnerGroup', 'LeasePrintTemplate', 'ConditionTemplate', 'Quotation'] as $resource) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $ability) {
                $this->assertDatabaseHas('permissions', [
                    'name' => "{$ability}:{$resource}",
                    'guard_name' => 'web',
                ]);
            }
        }
    }

    public function test_the_pms_admin_role_can_access_the_pms_panel_and_holds_every_pms_permission(): void
    {
        $this->seed(PMSPermissionsSeeder::class);

        $role = Role::where('name', 'pms-admin')->first();

        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('Access:MultipleSystems'));
        $this->assertTrue($role->hasPermissionTo('ViewAny:Property'));
        $this->assertTrue($role->hasPermissionTo('Delete:Lease'));
        $this->assertTrue($role->hasPermissionTo('View:PMSOverviewWidget'));
        $this->assertTrue($role->hasPermissionTo('View:PmsRevenueChartWidget'));
    }

    public function test_admin_and_super_admin_also_get_the_pms_dashboard_widgets(): void
    {
        // AllPermissionsSeeder's blanket grant is scoped to whichever panel
        // is "current" when it runs (the default `mms` panel), so these two
        // pms-only widgets never actually reached admin/super_admin through
        // that seeder alone — this one has to cover them explicitly.
        $this->seed(PMSPermissionsSeeder::class);

        foreach (['admin', 'super_admin', 'super-admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();

            $this->assertNotNull($role);
            $this->assertTrue($role->hasPermissionTo('View:PMSOverviewWidget'), "{$roleName} is missing View:PMSOverviewWidget");
            $this->assertTrue($role->hasPermissionTo('View:PmsRevenueChartWidget'), "{$roleName} is missing View:PmsRevenueChartWidget");
        }
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->seed(PMSPermissionsSeeder::class);
        $this->seed(PMSPermissionsSeeder::class);

        $role = Role::where('name', 'pms-admin')->first();

        $this->assertNotNull($role);
        $this->assertSame(1, Role::where('name', 'pms-admin')->count());
    }
}
