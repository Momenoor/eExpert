<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatterNote extends Model
{
    protected $fillable = [
        'matter_id',
        'note',
        'user_id',
    ];

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
