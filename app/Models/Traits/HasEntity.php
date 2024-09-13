<?php

namespace App\Models\Traits;

use App\Models\Entity;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasEntity
{
    public function name()
    {
        return Attribute::make(
            get: fn() => $this->entity ? $this->entity->name : null,
        );
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
