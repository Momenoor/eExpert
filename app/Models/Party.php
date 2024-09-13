<?php

namespace App\Models;

use App\Models\Traits\HasRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Party extends Model
{
    use HasFactory, LogsActivity, HasRole;

    protected static bool $logOnlyDirty = true;

    protected $table = 'entities';

    protected static bool $submitEmptyLogs = false;

    protected array $logAttributes = [
        'name',
        'phone',
        'fax',
        'address',
        'email',
        'type',
        'extra',
        'parent_id',
        'user_id',
    ];
    protected $fillable = [
        'name',
        'phone',
        'fax',
        'address',
        'email',
        'type',
        'parent_id',
        'user_id',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('party', function ($query) {
            $query->whereHas('roles', function ($query) {
                $query->where('role_name', 'party');
            });
        });
//        static::creating(function ($query) {
//            $query->user_id = auth()->id();
//        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function scopeNotBlackList()
    {
        return $this->where('black_list', false);
    }

    public function parent()
    {
        return $this->belongsTo(Party::class, 'parent_id');
    }


}
