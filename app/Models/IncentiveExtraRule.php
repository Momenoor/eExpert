<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;


class IncentiveExtraRule extends Model
{
    use LogsActivity;

    protected $fillable = [
        'min_count',
        'max_count',
        'extra_percentage',
    ];

    protected $casts = [
        'min_count' => 'integer',
        'max_count' => 'integer',
        'extra_percentage' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }
}
