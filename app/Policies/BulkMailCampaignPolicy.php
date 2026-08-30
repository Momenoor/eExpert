<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BulkMailCampaign;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BulkMailCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BulkMailCampaign');
    }

    public function view(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('View:BulkMailCampaign');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BulkMailCampaign');
    }

    public function update(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('Update:BulkMailCampaign');
    }

    public function delete(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('Delete:BulkMailCampaign');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BulkMailCampaign') || $authUser->can('delete_any_bulk_mail_campaign');
    }

    public function restore(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('Restore:BulkMailCampaign');
    }

    public function forceDelete(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('ForceDelete:BulkMailCampaign');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BulkMailCampaign');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BulkMailCampaign');
    }

    public function replicate(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('Replicate:BulkMailCampaign');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BulkMailCampaign');
    }

    public function send(AuthUser $authUser, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $authUser->can('Send:BulkMailCampaign') || $authUser->can('send_bulk_mail_campaign');
    }
}
