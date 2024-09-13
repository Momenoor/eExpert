<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Procedure extends Model
{
    use HasFactory,LogsActivity;

    protected static $logOnlyDirty = true;

    protected static $submitEmptyLogs = false;

    protected $logAttributes  = [
        'type',
        'datetime',
        'description',
        'link',
        'link_type',
        'matter_id',
    ];
    protected $fillable = [
        'type',
        'datetime',
        'description',
        'link',
        'link_type',
        'matter_id',
    ];

    protected $dates = [
        'datetime'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function scopeReceivedDate($query)
    {
        return $query->where('type', 'received_date');
    }

    public function scopeNextSessionDate($query)
    {
        return $query->where('type', 'next_session_date');
    }
}
