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

        $superAdmin = Role::where('name', 'super_admin')->first();
        $this->assertNotNull($superAdmin);
        $this->assertGreaterThanOrEqual(count($shieldPermissions), $superAdmin->permissions()->count());

        $admin = Role::where('name', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertGreaterThan(0, $admin->permissions()->count());
    }
}
