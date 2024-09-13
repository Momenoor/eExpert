<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Note extends Model
{
    use HasFactory,LogsActivity;

    protected static $logOnlyDirty = true;

    protected static $submitEmptyLogs = false;

    protected $logAttributes = [
        'matter_id',
        'user_id',
        'text',
        'datetime'
    ];
    protected $fillable = [
        'matter_id',
        'user_id',
        'text',
        'datetime'
    ];

    protected $dates = [
        'datetime'
    ];

    protected $with = [
        'user'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($query) {
            $query->user_id = auth()->id();
            $query->datetime = now();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function user()
    {
        return  $this->belongsTo(User::class);
    }
}
