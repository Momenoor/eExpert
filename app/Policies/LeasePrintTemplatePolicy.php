<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeasePrintTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeasePrintTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeasePrintTemplate');
    }

    public function view(AuthUser $authUser, LeasePrintTemplate $leasePrintTemplate): bool
    {
        return $authUser->can('View:LeasePrintTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeasePrintTemplate');
    }

    public function update(AuthUser $authUser, LeasePrintTemplate $leasePrintTemplate): bool
    {
        return $authUser->can('Update:LeasePrintTemplate');
    }

    public function delete(AuthUser $authUser, LeasePrintTemplate $leasePrintTemplate): bool
    {
        return $authUser->can('Delete:LeasePrintTemplate');
    }

    public function restore(AuthUser $authUser, LeasePrintTemplate $leasePrintTemplate): bool
    {
        return $authUser->can('Restore:LeasePrintTemplate');
    }

    public function forceDelete(AuthUser $authUser, LeasePrintTemplate $leasePrintTemplate): bool
    {
        return $authUser->can('ForceDelete:LeasePrintTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeasePrintTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeasePrintTemplate');
    }

    public function replicate(AuthUser $authUser, LeasePrintTemplate $leasePrintTemplate): bool
    {
        return $authUser->can('Replicate:LeasePrintTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeasePrintTemplate');
    }
}
