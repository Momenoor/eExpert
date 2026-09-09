<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeProfile;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Employment records carry passport, visa and bank details, so viewing one is a
 * materially different permission from viewing the party it hangs off.
 */
class EmployeeProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeProfile');
    }

    public function view(AuthUser $authUser, EmployeeProfile $employeeProfile): bool
    {
        return $authUser->can('View:EmployeeProfile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeProfile');
    }

    public function update(AuthUser $authUser, EmployeeProfile $employeeProfile): bool
    {
        return $authUser->can('Update:EmployeeProfile');
    }

    public function delete(AuthUser $authUser, EmployeeProfile $employeeProfile): bool
    {
        return $authUser->can('Delete:EmployeeProfile');
    }

    public function restore(AuthUser $authUser, EmployeeProfile $employeeProfile): bool
    {
        return $authUser->can('Restore:EmployeeProfile');
    }

    public function forceDelete(AuthUser $authUser, EmployeeProfile $employeeProfile): bool
    {
        return $authUser->can('ForceDelete:EmployeeProfile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EmployeeProfile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EmployeeProfile');
    }

    public function replicate(AuthUser $authUser, EmployeeProfile $employeeProfile): bool
    {
        return $authUser->can('Replicate:EmployeeProfile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EmployeeProfile');
    }
}
