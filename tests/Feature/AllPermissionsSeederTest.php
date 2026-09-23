<?php

declare(strict_types=1);

namespace Tests\Feature;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\AllPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_permissions_seeder_creates_all_shield_and_policy_permissions(): void
    {
        $this->seed(AllPermissionsSeeder::class);

        $shieldPermissions = FilamentShield::getEntitiesPermissions();

        foreach ($shieldPermissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $this->assertDatabaseHas('permissions', [
            'name' => 'Access:MultipleSystems',
            'guard_name' => 'web',
        ]);
    }

    public function test_all_permissions_seeder_creates_no_roles(): void
    {
        $this->seed(AllPermissionsSeeder::class);

        // super_admin bypasses every permission check via Shield's own
        // Gate::before (config/filament-shield.php `super_admin.define_via_gate`),
        // so it needs no seeded role — creating one, if wanted, is
        // InstallWizard::createAdmin()'s job when the admin account is made.
        $this->assertSame(0, Role::count());
    }
}
