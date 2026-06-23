<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncentiveMetaAdjustment extends Model
{
    protected $fillable = [
        'field_name',
        'field_value',
        'percentage_adjustment',
    ];

    protected $casts = [
        'percentage_adjustment' => 'float',
    ];
}
