<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Observers\AttachmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MatterRequest extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $fillable = [
        'matter_id',
        'request_by',
        'type',
        'status',
        'comment',
        'approved_by',
        'approved_at',
        'approved_comment',
        'extra',
        'name',
        'path',
        'size',
        'extension',
        'user_id',
    ];

    protected $casts = [
        'extra' => 'array',
        'approved_at' => 'datetime',
        'type' => RequestType::class,
        'status' => RequestStatus::class,
    ];

    protected static function booted(): void
    {
        static::deleting(function (MatterRequest $request) {
            if ($request->type == RequestType::REVIEW_REPORT && $request->matter->review_count > 0) {
                $request->matter->decrement('review_count');
            }
            $request->attachments->each->delete();
        });
    }

    public function matter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function requestBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'request_by');
    }

    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attachment::class, 'matter_request_id');
    }
}
