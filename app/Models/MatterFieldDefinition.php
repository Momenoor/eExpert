<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MatterFieldDefinition extends Model
{
    protected $fillable = [
        'label',
        'type',
        'required',
        'options',
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'json',
    ];

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(Type::class, 'matter_field_definition_type');
    }
}
