<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpertType extends Model
{
    protected $fillable = ['name', 'parent_id'];

    public function experts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Expert::class, 'expert_expert_type')
            ->withTimestamps();
    }

    public function matterExperts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MatterExpert::class);
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExpertType::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpertType::class, 'parent_id');
    }

}
