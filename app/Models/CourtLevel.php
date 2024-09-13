<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtLevel extends Model
{
    protected $fillable = [
        'name'
    ];

    public function courts()
    {
        return $this->belongsToMany(Court::class);
    }

    public function matters()
    {
        return $this->hasMany(Matter::class);
    }

}
