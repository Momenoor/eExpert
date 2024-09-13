<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'fax',
        'address',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function experts()
    {
        return $this->hasMany(Expert::class);
    }

    public function parties()
    {
        return $this->hasMany(Party::class);
    }

    public function brokers()
    {
        return $this->hasMany(Broker::class);
    }

    public function courts()
    {
        return $this->hasMany(Court::class);
    }


}
