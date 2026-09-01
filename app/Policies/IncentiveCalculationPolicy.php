<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IncentiveCalculation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IncentiveCalculationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IncentiveCalculation');
    }

    public function view(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('View:IncentiveCalculation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IncentiveCalculation');
    }

    public function update(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('Update:IncentiveCalculation');
    }

    public function delete(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('Delete:IncentiveCalculation');
    }

    public function restore(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('Restore:IncentiveCalculation');
    }

    public function forceDelete(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('ForceDelete:IncentiveCalculation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IncentiveCalculation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IncentiveCalculation');
    }

    public function replicate(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('Replicate:IncentiveCalculation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IncentiveCalculation');
    }

    public function runCalculation(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('RunCalculation:IncentiveCalculation');
    }

    public function finalize(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('Finalize:IncentiveCalculation');
    }

    public function print(AuthUser $authUser, IncentiveCalculation $incentiveCalculation): bool
    {
        return $authUser->can('Print:IncentiveCalculation');
    }
}
