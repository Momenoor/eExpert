<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatterMeta extends Model
{
    protected $fillable = [
        'matter_id',
        'field_name',
        'field_value',
    ];

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }
}
