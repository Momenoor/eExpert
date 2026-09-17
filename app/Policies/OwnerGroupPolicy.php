<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OwnerGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OwnerGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OwnerGroup');
    }

    public function view(AuthUser $authUser, OwnerGroup $ownerGroup): bool
    {
        return $authUser->can('View:OwnerGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OwnerGroup');
    }

    public function update(AuthUser $authUser, OwnerGroup $ownerGroup): bool
    {
        return $authUser->can('Update:OwnerGroup');
    }

    public function delete(AuthUser $authUser, OwnerGroup $ownerGroup): bool
    {
        return $authUser->can('Delete:OwnerGroup');
    }

    public function restore(AuthUser $authUser, OwnerGroup $ownerGroup): bool
    {
        return $authUser->can('Restore:OwnerGroup');
    }

    public function forceDelete(AuthUser $authUser, OwnerGroup $ownerGroup): bool
    {
        return $authUser->can('ForceDelete:OwnerGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OwnerGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OwnerGroup');
    }

    public function replicate(AuthUser $authUser, OwnerGroup $ownerGroup): bool
    {
        return $authUser->can('Replicate:OwnerGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OwnerGroup');
    }
}
