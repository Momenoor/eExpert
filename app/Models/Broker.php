<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broker extends Model
{
    protected $fillable = [
        'entity_id',
        'broker_type_id',
        'matter_commission_rate',
        'bank_name',
        'bank_account_number',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function brokerType()
    {
        return $this->belongsTo(BrokerType::class);
    }

    public function matters()
    {
        return $this->belongsToMany(Matter::class, 'matter_brokers')
            ->withPivot('broker_type_id', 'matter_commission_rate') // Include additional pivot fields if necessary
            ->withTimestamps();
    }

    public function matterBrokers()
    {
        return $this->hasMany(MatterBroker::class);
    }

}
