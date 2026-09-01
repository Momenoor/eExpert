<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MatterRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MatterRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MatterRequest');
    }

    public function view(AuthUser $authUser, MatterRequest $request): bool
    {
        return $authUser->can('View:MatterRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MatterRequest');
    }

    public function update(AuthUser $authUser, MatterRequest $request): bool
    {
        return $authUser->can('Update:MatterRequest');
    }

    public function delete(AuthUser $authUser, MatterRequest $request): bool
    {
        return $authUser->can('Delete:MatterRequest');
    }

    public function restore(AuthUser $authUser, MatterRequest $request): bool
    {
        return $authUser->can('Restore:MatterRequest');
    }

    public function forceDelete(AuthUser $authUser, MatterRequest $request): bool
    {
        return $authUser->can('ForceDelete:MatterRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MatterRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MatterRequest');
    }

    public function replicate(AuthUser $authUser, MatterRequest $request): bool
    {
        return $authUser->can('Replicate:MatterRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MatterRequest');
    }
}
