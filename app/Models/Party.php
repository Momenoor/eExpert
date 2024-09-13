<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    protected $fillable = [
        'entity_id',
        'party_type_id',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function partyType()
    {
        return $this->belongsTo(PartyType::class);
    }

    public function matterParties()
    {
        return $this->hasMany(MatterParty::class);
    }

}
