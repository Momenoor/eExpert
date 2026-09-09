<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Filing a leave request and deciding one are separate abilities: every employee
 * needs Create, only HR needs Approve.
 */
class LeaveRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeaveRequest');
    }

    public function view(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('View:LeaveRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeaveRequest');
    }

    public function update(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('Update:LeaveRequest');
    }

    public function delete(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('Delete:LeaveRequest');
    }

    public function restore(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('Restore:LeaveRequest');
    }

    public function forceDelete(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('ForceDelete:LeaveRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeaveRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeaveRequest');
    }

    public function replicate(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('Replicate:LeaveRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeaveRequest');
    }

    public function approve(AuthUser $authUser, LeaveRequest $leaveRequest): bool
    {
        return $authUser->can('Approve:LeaveRequest');
    }

    /**
     * Whether this user acts for the office rather than only for themselves.
     *
     * Everyone else may file a request against their own party and see their own
     * requests, and nothing else. Deliberately the same permission that decides
     * requests: the people who approve leave here are the people who file it on
     * behalf of others and who need to see everybody's. A second permission would
     * be one more thing to keep in step with the first, and the failure mode of
     * them drifting apart is an employee reading a colleague's sick note.
     */
    public function manageOthers(AuthUser $authUser): bool
    {
        return $authUser->can('Approve:LeaveRequest');
    }
}
