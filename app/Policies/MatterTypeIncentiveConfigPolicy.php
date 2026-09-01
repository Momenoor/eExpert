<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MatterTypeIncentiveConfig;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MatterTypeIncentiveConfigPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MatterTypeIncentiveConfig');
    }

    public function view(AuthUser $authUser, MatterTypeIncentiveConfig $matterTypeIncentiveConfig): bool
    {
        return $authUser->can('View:MatterTypeIncentiveConfig');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MatterTypeIncentiveConfig');
    }

    public function update(AuthUser $authUser, MatterTypeIncentiveConfig $matterTypeIncentiveConfig): bool
    {
        return $authUser->can('Update:MatterTypeIncentiveConfig');
    }

    public function delete(AuthUser $authUser, MatterTypeIncentiveConfig $matterTypeIncentiveConfig): bool
    {
        return $authUser->can('Delete:MatterTypeIncentiveConfig');
    }

    public function restore(AuthUser $authUser, MatterTypeIncentiveConfig $matterTypeIncentiveConfig): bool
    {
        return $authUser->can('Restore:MatterTypeIncentiveConfig');
    }

    public function forceDelete(AuthUser $authUser, MatterTypeIncentiveConfig $matterTypeIncentiveConfig): bool
    {
        return $authUser->can('ForceDelete:MatterTypeIncentiveConfig');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MatterTypeIncentiveConfig');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MatterTypeIncentiveConfig');
    }

    public function replicate(AuthUser $authUser, MatterTypeIncentiveConfig $matterTypeIncentiveConfig): bool
    {
        return $authUser->can('Replicate:MatterTypeIncentiveConfig');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MatterTypeIncentiveConfig');
    }
}
