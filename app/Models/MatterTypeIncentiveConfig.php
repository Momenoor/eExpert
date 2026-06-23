<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MatterTypeIncentiveConfig extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
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

    public function matterTypes(): HasMany
    {
        return $this->hasMany(Type::class, 'incentive_config_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(MatterTypeIncentiveTier::class, 'config_id');
    }
}
