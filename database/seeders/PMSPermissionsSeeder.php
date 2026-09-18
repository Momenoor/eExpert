<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PMS-only role/permission grants — separate from `AllPermissionsSeeder`
 * (which grants every Shield permission to `super_admin`/`super-admin`/
 * `admin` regardless of module). This seeder exists for a role scoped to
 * *just* PMS: full CRUD on every PMS resource, its dashboard widgets, plus
 * `Access:MultipleSystems` — without that permission, `User::canAccessPanel()`
 * refuses the `pms` panel outright, so a PMS-only role would otherwise hold
 * every PMS permission and still never be able to log in and use any of them.
 */
class PMSPermissionsSeeder extends Seeder
{
    private const PMS_RESOURCES = [
        'Property',
        'Lease',
        'Tenant',
        'OwnerProfile',
        'OwnerGroup',
        'LeasePrintTemplate',
        'ConditionTemplate',
        'Quotation',
    ];

    private const ABILITIES = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete',
        'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny',
        'Replicate', 'Reorder',
    ];

    /**
     * Gates the PMS panel switcher and direct access to the `pms` panel
     * itself — see `User::canAccessPanel()` and `SystemSwitcher`.
     */
    private const PANEL_ACCESS_PERMISSION = 'Access:MultipleSystems';

    /**
     * Dashboard widgets that only exist on the `pms` panel. `AllPermissionsSeeder`
     * discovers pages/widgets via `FilamentShield::getEntitiesPermissions()`,
     * which is scoped to whichever panel is "current" when it runs — the
     * default panel is `mms` (see `MmsPanelProvider`), so these two never
     * actually reached `admin`/`super_admin`/`super-admin` despite that
     * seeder's grant looking unconditional.
     */
    private const PMS_WIDGETS = [
        'View:PMSOverviewWidget',
        'View:PmsRevenueChartWidget',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect(self::PMS_RESOURCES)
            ->crossJoin(self::ABILITIES)
            ->map(fn (array $pair): string => "{$pair[1]}:{$pair[0]}")
            ->push(self::PANEL_ACCESS_PERMISSION)
            ->concat(self::PMS_WIDGETS)
            ->values();

        $permissions = $permissionNames->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
        );

        $this->command?->info('✓ '.$permissions->count().' PMS permissions ready.');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // A role scoped to PMS alone — every PMS resource, plus the one
        // permission that actually lets it reach the panel those resources
        // live in.
        foreach (['pms-admin', 'super_admin', 'super-admin', 'admin'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($permissions);

            $this->command?->info("✓ Role [{$roleName}] → {$permissions->count()} PMS permissions assigned.");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('✓ Done. Permission cache cleared.');
    }
}
