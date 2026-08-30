<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IncentiveMetaAdjustment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IncentiveMetaAdjustmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IncentiveMetaAdjustment');
    }

    public function view(AuthUser $authUser, IncentiveMetaAdjustment $incentiveMetaAdjustment): bool
    {
        return $authUser->can('View:IncentiveMetaAdjustment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IncentiveMetaAdjustment');
    }

    public function update(AuthUser $authUser, IncentiveMetaAdjustment $incentiveMetaAdjustment): bool
    {
        return $authUser->can('Update:IncentiveMetaAdjustment');
    }

    public function delete(AuthUser $authUser, IncentiveMetaAdjustment $incentiveMetaAdjustment): bool
    {
        return $authUser->can('Delete:IncentiveMetaAdjustment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:IncentiveMetaAdjustment');
    }

    public function restore(AuthUser $authUser, IncentiveMetaAdjustment $incentiveMetaAdjustment): bool
    {
        return $authUser->can('Restore:IncentiveMetaAdjustment');
    }

    public function forceDelete(AuthUser $authUser, IncentiveMetaAdjustment $incentiveMetaAdjustment): bool
    {
        return $authUser->can('ForceDelete:IncentiveMetaAdjustment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IncentiveMetaAdjustment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IncentiveMetaAdjustment');
    }

    public function replicate(AuthUser $authUser, IncentiveMetaAdjustment $incentiveMetaAdjustment): bool
    {
        return $authUser->can('Replicate:IncentiveMetaAdjustment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IncentiveMetaAdjustment');
    }
}
