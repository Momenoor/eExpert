<?php

namespace App\Console\Commands;

use App\Enums\BulkMailCampaignStatus;
use App\Jobs\SendBulkMailBatch;
use App\Models\BulkMailCampaign;
use Illuminate\Console\Command;

class SendBulkCampaignsCommand extends Command
{
    protected $signature = 'mail:send-bulk-campaigns';

    protected $description = 'Find all active campaigns and dispatch sending jobs.';

    public function handle(): void
    {
        $campaigns = BulkMailCampaign::where('status', BulkMailCampaignStatus::Active)
            ->where(function ($query) {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->get();

        foreach ($campaigns as $campaign) {
            SendBulkMailBatch::dispatch($campaign->id);
            $this->info("Dispatched batch for campaign: {$campaign->name}");
        }
    }
}
