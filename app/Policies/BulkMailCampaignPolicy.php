<?php

namespace App\Policies;

use App\Models\BulkMailCampaign;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BulkMailCampaignPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:BulkMailCampaign');
    }

    public function view(User $user, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $user->can('View:BulkMailCampaign');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:BulkMailCampaign');
    }

    public function update(User $user, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $user->can('Update:BulkMailCampaign');
    }

    public function delete(User $user, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $user->can('Delete:BulkMailCampaign');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_bulk_mail_campaign');
    }

    public function send(User $user, BulkMailCampaign $bulkMailCampaign): bool
    {
        return $user->can('send_bulk_mail_campaign');
    }
}
