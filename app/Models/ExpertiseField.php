<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpertiseField extends Model
{
    protected $fillable = [
        'name',
    ];

    public function experts()
    {
        return $this->hasMany(Expert::class);
    }

}
