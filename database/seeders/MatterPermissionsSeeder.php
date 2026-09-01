<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

    private const ROLE_PERMISSIONS = [
        'super_admin' => '*',
        'admin' => [
            'ViewAny:Matter', 'View:Matter', 'Create:Matter', 'Update:Matter',
            'Delete:Matter', 'ForceDelete:Matter', 'ForceDeleteAny:Matter',
            'Restore:Matter', 'RestoreAny:Matter', 'Replicate:Matter',
            'Export:Matter', 'Import:Matter',
            'InitialReport:Matter', 'FinalReport:Matter',
            'CreateNote:Matter', 'UpdateNote:Matter', 'DeleteNote:Matter',
            'CreateRequest:Matter', 'ApproveRequest:Matter', 'RejectRequest:Matter',
            'CreateFee:Matter', 'UpdateFee:Matter', 'DeleteFee:Matter',
            'CollectFee:Matter', 'UpdateAllocation:Matter', 'DeleteAllocation:Matter',
            'CreateAttachment:Matter', 'DeleteAttachment:Matter',
        ],
        'expert' => [
            'ViewAny:Matter', 'View:Matter',
            'CreateNote:Matter', 'UpdateNote:Matter',
            'CreateRequest:Matter',
            'CreateAttachment:Matter',
            'InitialReport:Matter', 'FinalReport:Matter',
        ],
        'party' => [
            'ViewAny:Matter', 'View:Matter',
        ],
        'accountant' => [
            'ViewAny:Matter', 'View:Matter',
            'CreateFee:Matter', 'UpdateFee:Matter', 'DeleteFee:Matter',
            'CollectFee:Matter', 'UpdateAllocation:Matter', 'DeleteAllocation:Matter',
        ],
    ];

    public function run(): void
    {
        // 1. Clear cache first
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Create all permissions and collect as objects
        $created = collect(self::PERMISSIONS)->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        $this->command->info('✓ '.$created->count().' permissions ready.');

        // 3. Clear cache again so roles can find newly created permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 4. Assign to roles using Permission objects (not strings) to avoid cache issues
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $permissionObjects = $permissions === '*'
                ? $created
                : $created->filter(fn ($p) => in_array($p->name, $permissions));

            $role->syncPermissions($permissionObjects);

            $count = $permissions === '*' ? $created->count() : count($permissions);
            $this->command->info("✓ Role [{$roleName}] → {$count} permissions assigned.");
        }

        // 5. Final cache clear
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('✓ Done. Permission cache cleared.');
    }
}
