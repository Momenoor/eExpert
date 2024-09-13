<?php

namespace App\Models;

use App\Models\Traits\HasEntity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasEntity;

    protected $fillable = [
        'entity_id',
    ];

    public function levels()
    {
        return $this->belongsToMany(CourtLevel::class);
    }

    public function matters()
    {
        return $this->hasMany(Matter::class);
    }


}
