<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntityRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'role_id',
        'description',
        'expert_field_id',
        'party_type',
        'is_blacklisted',
        'commission_rate',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
    ];

    public function expertField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExpertField::class, 'expert_field_id');
    }

}
