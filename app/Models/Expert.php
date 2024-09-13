<?php

namespace App\Models;

use App\Models\Traits\HasEntity;
use Illuminate\Database\Eloquent\Model;

class Expert extends Model
{
    use HasEntity;

    protected $fillable = [
        'entity_id',
        'expertise_field_id'
    ];

    /**
     * Relationships
     */


    public function expertiseField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExpertiseField::class);
    }

    public function matters()
    {
        return $this->belongsToMany(Matter::class, 'matter_experts')
            ->withPivot('expert_type_id') // Include additional pivot fields if needed
            ->withTimestamps();
    }

    public function matterExperts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MatterExpert::class);
    }

    public function types(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ExpertType::class, 'expert_expert_type')
            ->withTimestamps();
    }

}
