<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\PMSPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_it_creates_the_panel_access_and_widget_permissions(): void
    {
        $this->seed(PMSPermissionsSeeder::class);

        foreach (['Access:MultipleSystems', 'View:PMSOverviewWidget', 'View:PmsRevenueChartWidget'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_it_creates_no_roles(): void
    {
        $this->seed(PMSPermissionsSeeder::class);

        // super_admin bypasses every permission check via Shield's own
        // Gate::before, so it needs no seeded role or grant here; any other
        // role (e.g. a PMS-only office role) is built and assigned these
        // permissions later through Shield's own Role resource.
        $this->assertSame(0, Role::count());
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->seed(PMSPermissionsSeeder::class);
        $this->seed(PMSPermissionsSeeder::class);

        $this->assertSame(1, Permission::where('name', 'Access:MultipleSystems')->count());
    }
}
