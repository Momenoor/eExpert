<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Quotation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class QuotationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Quotation');
    }

    public function view(AuthUser $authUser, Quotation $quotation): bool
    {
        return $authUser->can('View:Quotation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Quotation');
    }

    public function update(AuthUser $authUser, Quotation $quotation): bool
    {
        return $authUser->can('Update:Quotation');
    }

    public function delete(AuthUser $authUser, Quotation $quotation): bool
    {
        return $authUser->can('Delete:Quotation');
    }

    public function restore(AuthUser $authUser, Quotation $quotation): bool
    {
        return $authUser->can('Restore:Quotation');
    }

    public function forceDelete(AuthUser $authUser, Quotation $quotation): bool
    {
        return $authUser->can('ForceDelete:Quotation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Quotation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Quotation');
    }

    public function replicate(AuthUser $authUser, Quotation $quotation): bool
    {
        return $authUser->can('Replicate:Quotation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Quotation');
    }
}
