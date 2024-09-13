<?php

namespace App\Models;

use App\Enum\ActiveStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtLevel extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => ActiveStatusEnum::class
    ];

    protected $fillable = [
        'name',
        'status',
    ];

}
