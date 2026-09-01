<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PartyLeave;
use Illuminate\Auth\Access\HandlesAuthorization;

class PartyLeavePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PartyLeave');
    }

    public function view(AuthUser $authUser, PartyLeave $partyLeave): bool
    {
        return $authUser->can('View:PartyLeave');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PartyLeave');
    }

    public function update(AuthUser $authUser, PartyLeave $partyLeave): bool
    {
        return $authUser->can('Update:PartyLeave');
    }

    public function delete(AuthUser $authUser, PartyLeave $partyLeave): bool
    {
        return $authUser->can('Delete:PartyLeave');
    }

    public function restore(AuthUser $authUser, PartyLeave $partyLeave): bool
    {
        return $authUser->can('Restore:PartyLeave');
    }

    public function forceDelete(AuthUser $authUser, PartyLeave $partyLeave): bool
    {
        return $authUser->can('ForceDelete:PartyLeave');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PartyLeave');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PartyLeave');
    }

    public function replicate(AuthUser $authUser, PartyLeave $partyLeave): bool
    {
        return $authUser->can('Replicate:PartyLeave');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PartyLeave');
    }

}