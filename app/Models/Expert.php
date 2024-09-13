<?php

namespace App\Models;

use App\Enum\EntitySubRoleEnum;
use App\Models\Traits\HasRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expert extends Model
{
    use HasFactory, HasRole;

    protected $table = 'entities';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'fax',
        'address',
        'expert_role',
        'expert_field_id',
        'user_id',
    ];

    protected $with = [
        'user',
        'expertField',
    ];

    protected $casts = [
        'expert_role' => EntitySubRoleEnum::class,
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope('expert', function ($query) {
            $query->whereHas('roles', function ($query) {
                $query->whereLike('name', '%expert%');
            });
        });
//        static::creating(function ($query) {
//            $query->user_id = auth()->id();
//        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expertField(): \Illuminate\Database\Eloquent\Relations\hasManyThrough
    {
        return $this->hasManyThrough(
            ExpertField::class,
            EntityRole::class,
            'entity_id',
            'id',
            'id',
            'expert_field_id'
        )
            ->select([
                'expert_fields.id',
                'expert_fields.name'
            ]);
    }


}
