<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;

class Account extends Model
{
    use HasFactory;

    protected $logAttributes  = [
        'name',
        'phone',
        'email',
        'gender',
        'avatar',
    ];
    protected $fillable = [
        'name',
        'phone',
        'email',
        'gender',
        'avatar',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function expert()
    {
        return $this->hasOne(Expert::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
