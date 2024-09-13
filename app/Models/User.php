<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends \Illuminate\Foundation\Auth\User
{
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->password)) {
                $user->password = bcrypt('password'); // Set default password
            }
        });
    }

    public function matterNotes()
    {
        return $this->hasMany(MatterNote::class);
    }

    public function entity()
    {
        return $this->hasOne(Entity::class);
    }

}
