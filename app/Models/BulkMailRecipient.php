<?php

namespace App\Models;

use App\Enums\BulkMailRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BulkMailRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'email',
        'name',
        'placeholders',
        'cc_emails',
        'status',
        'sent_at',
        'failed_at',
        'failure_reason',
        'message_id',
        'attempt_count',
        'unsubscribe_token',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'cc_emails' => 'array',
        'email'=> 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'status' => BulkMailRecipientStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($recipient) {
            if (empty($recipient->unsubscribe_token)) {
                $recipient->unsubscribe_token = Str::random(32);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BulkMailCampaign::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BulkMailLog::class, 'recipient_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', BulkMailRecipientStatus::Pending);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', BulkMailRecipientStatus::Failed);
    }

    public function scopeSent($query)
    {
        return $query->where('status', BulkMailRecipientStatus::Sent);
    }
}
