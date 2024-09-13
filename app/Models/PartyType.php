<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyType extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PartyType::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PartyType::class, 'parent_id');
    }

    public function parties()
    {
        return $this->hasMany(Party::class, 'party_type_id');
    }

    public function matterParties()
    {
        return $this->hasMany(MatterParty::class);
    }

}
