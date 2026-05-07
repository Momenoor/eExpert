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
use Webklex\PHPIMAP\ClientManager;

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
                $this->withMailerConfig($campaign, function () use ($campaign, $recipient) {

                    $message = Mail::to($recipient->email)
                        ->cc(array_merge($campaign->cc_emails ?? [], $recipient->cc_emails ?? []))
                        ->bcc($campaign->bcc_emails ?? [])
                        ->send(new BulkMailMessage($campaign, $recipient));
                    try {
                        $cm = new ClientManager($this->getIMAPConfig($campaign));
                        $client = $cm->account($campaign->from_sender_key);
                        $client->connect();
                        $sendFolder = $client->getFolder('Sent');
                        $sendFolder->appendMessage($message->getSymfonySentMessage()->toString(), ['\Seen'], now()->format("d-M-Y h:i:s O"));

                    } catch (\Exception $e) {
                        Log::error('Failed to connect to IMAP server for campaign: ' . $campaign->id . ', recipient: ' . $recipient->email, ['exception' => $e]);
                        return;
                    }

                });

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

    public function withMailerConfig(BulkMailCampaign $campaign, callable $callable)
    {
        $original = [
            'mail.default' => config('mail.default'),
            'mail.mailers.smtp.host' => config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.username' => config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => config('mail.mailers.smtp.password'),
            'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption'),
            'mail.from.address' => config('mail.from.address'),
            'mail.from.name' => config('mail.from.name'),
        ];
        try {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $campaign->sender_config['host'],
                'mail.mailers.smtp.port' => $campaign->sender_config['port'],
                'mail.mailers.smtp.username' => $campaign->sender_config['username'],
                'mail.mailers.smtp.password' => $campaign->sender_config['password'],
                'mail.mailers.smtp.encryption' => $campaign->sender_config['encryption'],
                'mail.from.address' => $campaign->sender_config['address'],
                'mail.from.name' => $campaign->sender_config['name'],
            ]);
            app('mail.manager')->purge('smtp');
            $callable();
        } catch (\Exception $e) {
            Log::error("Failed to execute mailer configuration callback: " . $e->getMessage());
            return false;
        } finally {
            config($original);
            app('mail.manager')->purge('smtp');
        }

    }

    /**
     * @param $campaign
     * @return \array[][]
     */
    function getIMAPConfig($campaign): array
    {
        return [
            'accounts' => [
                $campaign->from_sender_key => [
                    'host' => $campaign->sender_config['host'],
                    'port' => 993,
                    'encryption' => $campaign->sender_config['encryption'],
                    'username' => $campaign->sender_config['username'],
                    'password' => $campaign->sender_config['password'],
                    'protocol' => 'imap', //might also use imap, [pop3 or nntp (untested)]
                    'validate_cert' => true,
                    'authentication' => null,
                    'proxy' => [
                        'socket' => null,
                        'request_fulluri' => false,
                        'username' => null,
                        'password' => null,
                    ],
                    "timeout" => 30,
                    "extensions" => []
                ],
            ],
        ];
    }
}
