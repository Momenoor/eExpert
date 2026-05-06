<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkMailLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'recipient_id',
        'action',
        'metadata',
        'timestamp',
    ];

    protected $casts = [
        'metadata' => 'array',
        'timestamp' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BulkMailCampaign::class, 'campaign_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(BulkMailRecipient::class, 'recipient_id');
    }
}
