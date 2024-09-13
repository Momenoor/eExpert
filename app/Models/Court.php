<?php

namespace App\Models;

use App\Enum\ActiveStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Court extends Model
{
    use HasFactory, LogsActivity;

    protected static $logOnlyDirty = true;

    protected static $submitEmptyLogs = false;

    protected $logAttributes = [
        'name',
        'phone',
        'email',
        'address',
        'status',
    ];
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'status',
    ];

    protected $casts = [
        'status' => ActiveStatusEnum::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function matters()
    {
        return $this->hasMany(Matter::class);
    }
}
