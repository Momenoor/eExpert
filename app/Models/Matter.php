<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matter extends Model
{
    protected $fillable = [
        'year',
        'number',
        'received_at',
        'reported_at',
        'submitted_at',
        'next_session_at',
        'last_action_at',
        'court_id',
        'court_level_id',
        'matter_status_id',
        'matter_type_id',
        'parent_id',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class, 'court_id');
    }

    public function courtLevel()
    {
        return $this->belongsTo(CourtLevel::class, 'court_level_id');
    }

    public function matterStatus()
    {
        return $this->belongsTo(MatterStatus::class, 'matter_status_id');
    }

    public function matterType()
    {
        return $this->belongsTo(MatterType::class, 'matter_type_id');
    }

    public function parent()
    {
        return $this->belongsTo(Matter::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Matter::class, 'parent_id');
    }

    public function notes()
    {
        return $this->hasMany(MatterNote::class);
    }

    public function brokers()
    {
        return $this->belongsToMany(Broker::class, 'matter_brokers')
            ->withPivot(['broker_type_id', 'matter_commission_rate'])
            ->withTimestamps();
    }

    public function matterBrokers()
    {
        return $this->hasMany(MatterBroker::class);
    }

    public function parties()
    {
        return $this->belongsToMany(Party::class, 'matter_parties')
            ->withPivot(['party_type_id', 'parent_id'])
            ->withTimestamps();
    }

    public function matterParties()
    {
        return $this->hasMany(MatterParty::class);
    }

    public function experts()
    {
        return $this->belongsToMany(Expert::class, 'matter_experts')
            ->withPivot('expert_type_id')
            ->withTimestamps();
    }

    public function matterExperts()
    {
        return $this->hasMany(MatterExpert::class);
    }

}
