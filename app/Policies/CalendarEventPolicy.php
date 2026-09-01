<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalendarEvent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CalendarEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CalendarEvent');
    }

    public function view(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('View:CalendarEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CalendarEvent');
    }

    public function update(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('Update:CalendarEvent');
    }

    public function delete(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('Delete:CalendarEvent');
    }

    public function restore(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('Restore:CalendarEvent');
    }

    public function forceDelete(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('ForceDelete:CalendarEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CalendarEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CalendarEvent');
    }

    public function replicate(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('Replicate:CalendarEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CalendarEvent');
    }

    public function createSingle(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('CreateSingle:CalendarEvent');
    }

    public function createBulk(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('CreateBulk:CalendarEvent');
    }

    public function importFromOutlook(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('ImportFromOutlook:CalendarEvent');
    }

    public function syncToOutlook(AuthUser $authUser, CalendarEvent $calendarEvent): bool
    {
        return $authUser->can('SyncToOutlook:CalendarEvent');
    }
}
