<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MatterBroker extends Pivot
{
    public $incrementing = true;

    protected $fillable = [
        'matter_id',
        'broker_id',
        'matter_commission_rate',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function broker()
    {
        return $this->belongsTo(Broker::class);
    }
}
