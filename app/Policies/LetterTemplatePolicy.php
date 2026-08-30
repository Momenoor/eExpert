<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LetterTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LetterTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LetterTemplate');
    }

    public function view(AuthUser $authUser, LetterTemplate $letterTemplate): bool
    {
        return $authUser->can('View:LetterTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LetterTemplate');
    }

    public function update(AuthUser $authUser, LetterTemplate $letterTemplate): bool
    {
        return $authUser->can('Update:LetterTemplate');
    }

    public function delete(AuthUser $authUser, LetterTemplate $letterTemplate): bool
    {
        return $authUser->can('Delete:LetterTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LetterTemplate');
    }

    public function restore(AuthUser $authUser, LetterTemplate $letterTemplate): bool
    {
        return $authUser->can('Restore:LetterTemplate');
    }

    public function forceDelete(AuthUser $authUser, LetterTemplate $letterTemplate): bool
    {
        return $authUser->can('ForceDelete:LetterTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LetterTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LetterTemplate');
    }

    public function replicate(AuthUser $authUser, LetterTemplate $letterTemplate): bool
    {
        return $authUser->can('Replicate:LetterTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LetterTemplate');
    }
}
