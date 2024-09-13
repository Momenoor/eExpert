<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpertField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function experts(): \Illuminate\Database\Eloquent\Relations\belongsToMany
    {
        return $this->hasMany(ExpertField::class);
    }
}
