<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IncentiveExtraRule;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IncentiveExtraRulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IncentiveExtraRule');
    }

    public function view(AuthUser $authUser, IncentiveExtraRule $incentiveExtraRule): bool
    {
        return $authUser->can('View:IncentiveExtraRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IncentiveExtraRule');
    }

    public function update(AuthUser $authUser, IncentiveExtraRule $incentiveExtraRule): bool
    {
        return $authUser->can('Update:IncentiveExtraRule');
    }

    public function delete(AuthUser $authUser, IncentiveExtraRule $incentiveExtraRule): bool
    {
        return $authUser->can('Delete:IncentiveExtraRule');
    }

    public function restore(AuthUser $authUser, IncentiveExtraRule $incentiveExtraRule): bool
    {
        return $authUser->can('Restore:IncentiveExtraRule');
    }

    public function forceDelete(AuthUser $authUser, IncentiveExtraRule $incentiveExtraRule): bool
    {
        return $authUser->can('ForceDelete:IncentiveExtraRule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IncentiveExtraRule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IncentiveExtraRule');
    }

    public function replicate(AuthUser $authUser, IncentiveExtraRule $incentiveExtraRule): bool
    {
        return $authUser->can('Replicate:IncentiveExtraRule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IncentiveExtraRule');
    }
}
