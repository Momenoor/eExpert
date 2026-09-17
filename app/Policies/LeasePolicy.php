<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lease;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeasePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Lease');
    }

    public function view(AuthUser $authUser, Lease $lease): bool
    {
        return $authUser->can('View:Lease');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Lease');
    }

    public function update(AuthUser $authUser, Lease $lease): bool
    {
        return $authUser->can('Update:Lease');
    }

    public function delete(AuthUser $authUser, Lease $lease): bool
    {
        return $authUser->can('Delete:Lease');
    }

    public function restore(AuthUser $authUser, Lease $lease): bool
    {
        return $authUser->can('Restore:Lease');
    }

    public function forceDelete(AuthUser $authUser, Lease $lease): bool
    {
        return $authUser->can('ForceDelete:Lease');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Lease');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Lease');
    }

    public function replicate(AuthUser $authUser, Lease $lease): bool
    {
        return $authUser->can('Replicate:Lease');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Lease');
    }
}
