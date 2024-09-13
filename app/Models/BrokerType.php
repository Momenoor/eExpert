<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrokerType extends Model
{
    protected $fillable = ['name'];

    public function broker()
    {
        return $this->hasMany('App\Models\Broker');
    }

}
