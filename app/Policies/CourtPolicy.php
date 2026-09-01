<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Court;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CourtPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Court');
    }

    public function view(AuthUser $authUser, Court $court): bool
    {
        return $authUser->can('View:Court');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Court');
    }

    public function update(AuthUser $authUser, Court $court): bool
    {
        return $authUser->can('Update:Court');
    }

    public function delete(AuthUser $authUser, Court $court): bool
    {
        return $authUser->can('Delete:Court');
    }

    public function restore(AuthUser $authUser, Court $court): bool
    {
        return $authUser->can('Restore:Court');
    }

    public function forceDelete(AuthUser $authUser, Court $court): bool
    {
        return $authUser->can('ForceDelete:Court');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Court');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Court');
    }

    public function replicate(AuthUser $authUser, Court $court): bool
    {
        return $authUser->can('Replicate:Court');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Court');
    }
}
