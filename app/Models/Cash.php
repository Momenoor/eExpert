<?php

namespace App\Models;

use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Cash extends Model
{
    use HasFactory,LogsActivity;

    const PAID = 'paid';
    const UNPAID = 'unpaid';
    const PARTIAL = 'partial';
    const OVERPAID = 'overpaid';

    protected static $logOnlyDirty = true;

    protected static $submitEmptyLogs = false;

    protected $logAttributes  = [
        'datetime',
        'amount',
        'description',
        'type',
        'matter_id',
        'claim_id',
        'user_id',
    ];
    protected $fillable = [
        'datetime',
        'amount',
        'description',
        'type',
        'matter_id',
        'claim_id',
        'user_id',
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
    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function statusList()
    {
        return [
            self::PAID,
            self::UNPAID,
            self::OVERPAID,
            self::PARTIAL,
        ];
    }
}
