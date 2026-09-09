<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the payroll and vacation module.
 *
 * Written as a seeder rather than left to `shield:generate` for two reasons: the
 * module's custom abilities (Approve, HrApprove, FinanceApprove, Disburse) are
 * not among Shield's standard prefixes and would simply not be created; and this
 * runs on the live database with one command the operator issues themselves,
 * rather than as a side effect of a deployment.
 *
 * Idempotent — running it twice grants nothing twice.
 *
 *     php artisan db:seed --class=PayrollModulePermissionsSeeder
 */
class PayrollModulePermissionsSeeder extends Seeder
{
    /**
     * Shield's standard abilities, which every resource gets.
     *
     * Must stay in step with `policies.methods` in config/filament-shield.php.
     * A seeder that grants fewer than Shield generates leaves permissions nobody
     * holds; one that grants more invents permissions no policy answers to.
     */
    private const STANDARD = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore',
        'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder',
    ];

    /**
     * The abilities specific to this module, beyond the standard set.
     *
     * These must also be registered under `resources.manage` in
     * config/filament-shield.php. Shield's role editor SYNCS permissions, so an
     * ability it cannot see is stripped from every role the moment someone saves
     * one — which is exactly how these five went missing the first time.
     *
     * @var array<string, list<string>>
     */
    private const CUSTOM = [
        'LeaveRequest' => ['Approve'],
        'EmployeeLoan' => ['Approve'],
        'PayrollRun' => ['Generate', 'HrApprove', 'FinanceApprove', 'Disburse', 'ViewJournalVoucher'],
    ];

    /**
     * @var list<string>
     */
    private const SUBJECTS = [
        'EmployeeProfile', 'LeaveRequest', 'EmployeeLoan', 'PayrollRun',
    ];

    /**
     * Roles that should receive everything this module defines.
     *
     * Both spellings are listed because this database carries both — a role
     * named `super_admin` and another named `super-admin`. Granting to only one
     * would leave half the administrators locked out of payroll.
     */
    private const SUPER_ADMIN_ROLES = ['super_admin', 'super-admin'];

    public function run(): void
    {
        $names = [];

        foreach (self::SUBJECTS as $subject) {
            foreach ([...self::STANDARD, ...(self::CUSTOM[$subject] ?? [])] as $ability) {
                $names[] = "{$ability}:{$subject}";
            }
        }

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::SUPER_ADMIN_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            $role?->givePermissionTo($names);
        }

        // Spatie caches the permission map; without this the new grants are
        // invisible until the cache expires or someone clears it by hand.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf('%d payroll permissions ensured.', count($names)));
    }
}
