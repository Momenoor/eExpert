<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class IncentiveCalculationPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'RunCalculation:IncentiveCalculation',
        'Finalize:IncentiveCalculation',
        'Print:IncentiveCalculation',
    ];

    private const ROLES = ['super_admin', 'super-admin', 'admin'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->map(
            fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            // Additive only — never syncs, so existing role permissions are untouched.
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
