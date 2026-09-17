<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContractPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Contract');
    }

    public function view(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('View:Contract');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Contract');
    }

    public function update(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('Update:Contract');
    }

    public function delete(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('Delete:Contract');
    }

    public function restore(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('Restore:Contract');
    }

    public function forceDelete(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('ForceDelete:Contract');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Contract');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Contract');
    }

    public function replicate(AuthUser $authUser, Contract $contract): bool
    {
        return $authUser->can('Replicate:Contract');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Contract');
    }
}
