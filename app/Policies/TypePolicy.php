<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Type;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Type');
    }

    public function view(AuthUser $authUser, Type $type): bool
    {
        return $authUser->can('View:Type');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Type');
    }

    public function update(AuthUser $authUser, Type $type): bool
    {
        return $authUser->can('Update:Type');
    }

    public function delete(AuthUser $authUser, Type $type): bool
    {
        return $authUser->can('Delete:Type');
    }

    public function restore(AuthUser $authUser, Type $type): bool
    {
        return $authUser->can('Restore:Type');
    }

    public function forceDelete(AuthUser $authUser, Type $type): bool
    {
        return $authUser->can('ForceDelete:Type');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Type');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Type');
    }

    public function replicate(AuthUser $authUser, Type $type): bool
    {
        return $authUser->can('Replicate:Type');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Type');
    }
}
