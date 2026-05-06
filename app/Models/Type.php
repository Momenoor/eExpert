<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;


class Type extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'active'
    ];


    public function matters()
    {
        return $this->hasMany(Matter::class);
    }

    public function incentiveConfig()
    {
        return $this->hasOne(MatterTypeIncentiveConfig::class);
    }

    public function fieldDefinitions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MatterFieldDefinition::class, 'type_id');
    }
}
