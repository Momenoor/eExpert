<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MatterParty extends Pivot
{
    public $incrementing = true;
    protected $fillable = [
        'matter_id',
        'party_id',
        'party_type_id',
        'parent_id',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    // BelongsTo with Party
    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    // BelongsTo with PartyType
    public function partyType()
    {
        return $this->belongsTo(PartyType::class);
    }

    // Self-referencing parent relationship
    public function parent()
    {
        return $this->belongsTo(MatterParty::class, 'parent_id');
    }

    // Self-referencing child relationships
    public function children()
    {
        return $this->hasMany(MatterParty::class, 'parent_id');
    }

}
