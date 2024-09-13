<?php

namespace App\Models\Traits;

use App\Models\EntityRole;
use App\Models\Role;
use App\Models\SubRole;

trait HasRole
{

    public function roles(): \Illuminate\Database\Eloquent\Relations\belongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function subRoles(): \Illuminate\Database\Eloquent\Relations\hasManyThrough
    {
        return $this->hasManyThrough(SubRole::class, EntityRole::class, 'entity_id', 'entity_role_id', 'entity_sub_role_id');
    }
}
