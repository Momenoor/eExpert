<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantProfile;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TenantProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TenantProfile');
    }

    public function view(AuthUser $authUser, TenantProfile $tenantProfile): bool
    {
        return $authUser->can('View:TenantProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TenantProfile');
    }

    public function update(AuthUser $authUser, TenantProfile $tenantProfile): bool
    {
        return $authUser->can('Update:TenantProfile');
    }

    public function delete(AuthUser $authUser, TenantProfile $tenantProfile): bool
    {
        return $authUser->can('Delete:TenantProfile');
    }

    public function restore(AuthUser $authUser, TenantProfile $tenantProfile): bool
    {
        return $authUser->can('Restore:TenantProfile');
    }

    public function forceDelete(AuthUser $authUser, TenantProfile $tenantProfile): bool
    {
        return $authUser->can('ForceDelete:TenantProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TenantProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TenantProfile');
    }

    public function replicate(AuthUser $authUser, TenantProfile $tenantProfile): bool
    {
        return $authUser->can('Replicate:TenantProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TenantProfile');
    }
}
