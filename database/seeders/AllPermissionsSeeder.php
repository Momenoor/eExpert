<?php

declare(strict_types=1);

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ensures all application, Shield, and policy permissions exist in the database
 * and are assigned to appropriate roles.
 *
 * Idempotent — running multiple times safely creates only missing permissions.
 */
class AllPermissionsSeeder extends Seeder
{
    /**
     * Custom permissions that might not be automatically detected by Shield.
     * All permissions follow their resource, page, or widget directly.
     *
     * @var list<string>
     */
    private const ADDITIONAL_PERMISSIONS = [];

    /**
     * Roles that should receive all permissions.
     *
     * @var list<string>
     */
    private const SUPER_ADMIN_ROLES = [
        'super_admin',
        'super-admin',
    ];

    public function run(): void
    {
        // 1. Clear permission cache before querying/modifying
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Discover all permissions from Shield (Resources, Pages, Widgets, Custom)
        $shieldPermissions = FilamentShield::getEntitiesPermissions();

        // 3. Combine with additional permissions
        $allPermissions = collect([...$shieldPermissions, ...self::ADDITIONAL_PERMISSIONS])
            ->unique()
            ->filter()
            ->values();

        $createdCount = 0;
        $permissionModels = [];

        foreach ($allPermissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $createdCount++;
            }

            $permissionModels[] = $permission;
        }

        $this->command?->info("Processed {$allPermissions->count()} total permissions ({$createdCount} newly created).");

        // 4. Assign all permissions to super admin roles
        foreach (self::SUPER_ADMIN_ROLES as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($permissionModels);
            $this->command?->info("✓ Assigned all permissions to [{$roleName}] role.");
        }

        // 5. Assign appropriate permissions to admin role
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $adminRole->givePermissionTo($permissionModels);
        $this->command?->info('✓ Assigned permissions to [admin] role.');

        // 6. Clear permission cache so changes take effect immediately
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('✓ All permissions seeded and cache cleared successfully.');
    }
}
