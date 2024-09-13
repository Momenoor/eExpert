<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MatterExpert extends Pivot
{
    public $incrementing = true;
    protected $fillable = [
        'matter_id',
        'expert_id',
        'expert_type_id',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }

    public function expertType()
    {
        return $this->belongsTo(ExpertType::class);
    }


}
