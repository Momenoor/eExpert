<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OwnerProfile;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OwnerProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OwnerProfile');
    }

    public function view(AuthUser $authUser, OwnerProfile $ownerProfile): bool
    {
        return $authUser->can('View:OwnerProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OwnerProfile');
    }

    public function update(AuthUser $authUser, OwnerProfile $ownerProfile): bool
    {
        return $authUser->can('Update:OwnerProfile');
    }

    public function delete(AuthUser $authUser, OwnerProfile $ownerProfile): bool
    {
        return $authUser->can('Delete:OwnerProfile');
    }

    public function restore(AuthUser $authUser, OwnerProfile $ownerProfile): bool
    {
        return $authUser->can('Restore:OwnerProfile');
    }

    public function forceDelete(AuthUser $authUser, OwnerProfile $ownerProfile): bool
    {
        return $authUser->can('ForceDelete:OwnerProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OwnerProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OwnerProfile');
    }

    public function replicate(AuthUser $authUser, OwnerProfile $ownerProfile): bool
    {
        return $authUser->can('Replicate:OwnerProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OwnerProfile');
    }
}
