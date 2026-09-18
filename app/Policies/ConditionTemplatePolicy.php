<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConditionTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ConditionTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ConditionTemplate');
    }

    public function view(AuthUser $authUser, ConditionTemplate $conditionTemplate): bool
    {
        return $authUser->can('View:ConditionTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ConditionTemplate');
    }

    public function update(AuthUser $authUser, ConditionTemplate $conditionTemplate): bool
    {
        return $authUser->can('Update:ConditionTemplate');
    }

    public function delete(AuthUser $authUser, ConditionTemplate $conditionTemplate): bool
    {
        return $authUser->can('Delete:ConditionTemplate');
    }

    public function restore(AuthUser $authUser, ConditionTemplate $conditionTemplate): bool
    {
        return $authUser->can('Restore:ConditionTemplate');
    }

    public function forceDelete(AuthUser $authUser, ConditionTemplate $conditionTemplate): bool
    {
        return $authUser->can('ForceDelete:ConditionTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ConditionTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ConditionTemplate');
    }

    public function replicate(AuthUser $authUser, ConditionTemplate $conditionTemplate): bool
    {
        return $authUser->can('Replicate:ConditionTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ConditionTemplate');
    }
}
