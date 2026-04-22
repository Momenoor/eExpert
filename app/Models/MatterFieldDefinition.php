<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatterFieldDefinition extends Model
{
    protected $fillable = [
        'type_id',
        'label',
        'type',
        'required',
        'options',
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'json',
    ];

    public function matterType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }
}
