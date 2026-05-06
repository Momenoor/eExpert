<?php

namespace App\Jobs;

use App\Enums\BulkMailCampaignStatus;
use App\Enums\BulkMailRecipientStatus;
use App\Mail\BulkMailMessage;
use App\Models\BulkMailCampaign;
use App\Models\BulkMailLog;
use App\Models\BulkMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBulkMailBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $campaignId,
        public int $batchSize = 10
    )
    {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $campaign = BulkMailCampaign::find($this->campaignId);

        if (!$campaign || $campaign->status !== BulkMailCampaignStatus::Active) {
            return;
        }

        $remainingLimit = $campaign->getRemainingDailyLimit();

        if ($remainingLimit <= 0) {
            Log::info("Campaign {$this->campaignId} reached daily limit.");
            return;
        }

        $recipients = BulkMailRecipient::where('campaign_id', $this->campaignId)
            ->where('status', BulkMailRecipientStatus::Pending)
            ->limit(min($this->batchSize, $remainingLimit))
            ->get();

        if ($recipients->isEmpty()) {
            if (BulkMailRecipient::where('campaign_id', $this->campaignId)->where('status', BulkMailRecipientStatus::Pending)->count() === 0) {
                $campaign->update(['status' => BulkMailCampaignStatus::Completed]);
                // Notify creator (handled via observer or event later)
            }
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)
                    ->cc(array_merge($campaign->cc_emails ?? [], $recipient->cc_emails ?? []))
                    ->bcc($campaign->bcc_emails ?? [])
                    ->send(new BulkMailMessage($campaign, $recipient));

                $recipient->update([
                    'status' => BulkMailRecipientStatus::Sent,
                    'sent_at' => now(),
                ]);

                $campaign->increment('sent_count');

                BulkMailLog::create([
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'action' => 'sent',
                    'timestamp' => now(),
                ]);
            } catch (\Exception $e) {

                $recipient->increment('attempt_count');
                $retryAttempts = config('mail_senders.retry_attempts', 3);

                if ($recipient->attempt_count >= $retryAttempts) {
                    $recipient->update([
                        'status' => BulkMailRecipientStatus::Failed,
                        'failed_at' => now(),
                        'failure_reason' => $e->getMessage(),
                    ]);
                    $campaign->increment('failed_count');
                }

                BulkMailLog::create([
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'action' => 'failed',
                    'metadata' => ['error' => $e->getMessage()],
                    'timestamp' => now(),
                ]);

                Log::error("Failed to send bulk mail to {$recipient->email}: " . $e->getMessage());
            }
        }

        // Dispatch next batch if there are still pending recipients and daily limit not reached
        if ($campaign->getRemainingDailyLimit() > 0) {
            static::dispatch($this->campaignId, $this->batchSize)->delay(now()->addSeconds(30));
        }
    }
}
