<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * PMS-only permissions — separate from `AllPermissionsSeeder`/
 * `MatterPermissionsSeeder` since Shield's own discovery is scoped to
 * whichever panel is "current" when it runs (the default `mms` panel), so
 * it never reaches PMS's own resources/widgets or `Access:MultipleSystems`
 * (the permission `User::canAccessPanel()` requires to even open the `pms`
 * panel). No roles are created here — `super_admin` bypasses every check
 * via Shield's Gate::before, and any other role is built by the office
 * through Shield's own Role resource, using these rows as the assignable
 * list.
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

        $this->command?->info('✓ Done. Permission cache cleared.');
    }
}
