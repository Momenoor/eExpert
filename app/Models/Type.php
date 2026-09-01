<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Type extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $casts = [
        'active' => 'boolean',
        'allow_current_status_import' => 'boolean',
        'exclude_from_incentive_count' => 'boolean',
        'incentive_config_id' => 'integer',
    ];

    protected $fillable = [
        'name',
        'active',
        'incentive_trigger_type',
        'allow_current_status_import',
        'exclude_from_incentive_count',
        'incentive_config_id',
    ];

    public function matters()
    {
        return $this->hasMany(Matter::class);
    }

    public function incentiveConfig(): BelongsTo
    {
        return $this->belongsTo(MatterTypeIncentiveConfig::class, 'incentive_config_id');
    }

    public function fieldDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(MatterFieldDefinition::class, 'matter_field_definition_type');
    }
}
