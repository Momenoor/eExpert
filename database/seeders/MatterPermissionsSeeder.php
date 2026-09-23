<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the Matter-domain permissions Shield's own discovery can't infer
 * (report/note/request/fee/attachment actions aren't tied to a Filament
 * resource's standard CRUD abilities). No roles are created or granted
 * here — `super_admin` bypasses every check via Shield's Gate::before, and
 * any other role is built and assigned permissions later through Shield's
 * own Role resource.
 */
class MatterPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        // CRUD
        'ViewAny:Matter',
        'View:Matter',
        'Create:Matter',
        'Update:Matter',
        'Delete:Matter',
        'ForceDelete:Matter',
        'ForceDeleteAny:Matter',
        'Restore:Matter',
        'RestoreAny:Matter',
        'Replicate:Matter',
        'Export:Matter',
        'Import:Matter',
        // Reports
        'InitialReport:Matter',
        'FinalReport:Matter',
        // Notes
        'CreateNote:Matter',
        'UpdateNote:Matter',
        'DeleteNote:Matter',
        // Requests
        'CreateRequest:Matter',
        'ApproveRequest:Matter',
        'RejectRequest:Matter',
        // Fees
        'CreateFee:Matter',
        'UpdateFee:Matter',
        'DeleteFee:Matter',
        // Payments
        'CollectFee:Matter',
        'UpdateAllocation:Matter',
        'DeleteAllocation:Matter',
        // Attachments
        'CreateAttachment:Matter',
        'DeleteAttachment:Matter',
    ];

    public function run(): void
    {
        // 1. Clear cache first
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Create all permissions
        $created = collect(self::PERMISSIONS)->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        $this->command->info('✓ '.$created->count().' permissions ready.');

        // 3. Final cache clear so the new rows are visible immediately
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('✓ Done. Permission cache cleared.');
    }
}
