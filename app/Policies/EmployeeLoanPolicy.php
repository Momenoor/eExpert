<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeLoan;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmployeeLoanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmployeeLoan');
    }

    public function view(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('View:EmployeeLoan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmployeeLoan');
    }

    /**
     * A part-recovered advance is closed to editing, whatever the permission.
     *
     * The Build Schedule action authorises against this too, so both the form and
     * the rebuild are refused by the same rule rather than by two that could
     * drift apart. LoanScheduleService refuses independently as a last resort.
     */
    public function update(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('Update:EmployeeLoan') && $employeeLoan->isEditable();
    }

    public function delete(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('Delete:EmployeeLoan');
    }

    public function restore(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('Restore:EmployeeLoan');
    }

    public function forceDelete(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('ForceDelete:EmployeeLoan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EmployeeLoan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EmployeeLoan');
    }

    public function replicate(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('Replicate:EmployeeLoan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EmployeeLoan');
    }

    public function approve(AuthUser $authUser, EmployeeLoan $employeeLoan): bool
    {
        return $authUser->can('Approve:EmployeeLoan');
    }
}
