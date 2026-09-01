<?php

namespace App\Models;

use App\Enums\BulkMailCampaignStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;

class BulkMailCampaign extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'from_sender_key',
        'cc_emails',
        'bcc_emails',
        'has_attachment',
        'attachment_path',
        'attachment_disk',
        'daily_send_limit',
        'scheduled_at',
        'status',
        'placeholders',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by',
    ];

    protected $casts = [
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'placeholders' => 'array',
        'attachment_path' => 'array',
        'has_attachment' => 'boolean',
        'scheduled_at' => 'datetime',
        'status' => BulkMailCampaignStatus::class,
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BulkMailRecipient::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BulkMailLog::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function senderConfig(): Attribute
    {
        return new Attribute(
            get: fn () => Config::get("mail_senders.senders.{$this->from_sender_key}")
        );
    }

    public function getRemainingDailyLimit(): int
    {
        $sentToday = $this->recipients()
            ->where('status', 'sent')
            ->whereDate('sent_at', now()->toDateString())
            ->count();

        return max(0, $this->daily_send_limit - $sentToday);
    }

    public function renderBody(BulkMailRecipient $recipient, array $recipientPlaceholders = []): string
    {
        $body = $this->body;
        $body = $this->getDefaultPlaceholder($recipient, $recipientPlaceholders, $body);

        $sender = $this->sender_config;
        if ($sender && isset($sender['signature'])) {
            $body .= '<br><br>'.$sender['signature'];
        }

        return $body;
    }

    public function renderSubject(BulkMailRecipient $recipient, array $recipientPlaceholders = []): string
    {
        $subject = $this->subject;
        $subject = $this->getDefaultPlaceholder($recipient, $recipientPlaceholders, $subject);

        return $subject;
    }

    /**
     * @return array|mixed|string|string[]
     */
    private function getDefaultPlaceholder(BulkMailRecipient $recipient, array $recipientPlaceholders, mixed $body): mixed
    {
        $placeholder = array_merge($recipient->placeholders ?? [], $recipientPlaceholders);
        if (! isset($placeholder['name'])) {
            $placeholder['name'] = $recipient->name;
        }
        if (! isset($placeholder['email'])) {
            $placeholder['email'] = is_array($recipient->email) ? implode('; ', $recipient->email) : $recipient->email;
        }
        foreach ($placeholder as $key => $value) {
            $body = str_replace("{{{$key}}}", $value, $body);
        }

        return $body;
    }
}
