<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MatterTypeIncentiveConfig extends Model
{
    use LogsActivity;

    protected $fillable = [
        'type_id',
        'calculation_type',
        'fixed_percentage',
        'assistant_rate',
    ];

    protected $casts = [
        'fixed_percentage' => 'decimal:2',
        'assistant_rate' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    public function matterType(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(MatterTypeIncentiveTier::class, 'config_id');
    }
}
