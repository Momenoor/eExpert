<?php

namespace App\Models;

use App\Enums\PMS\Emirate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable set of legal clauses for one contract print format — so
 * changing the statutory text (a law amendment, say) is an admin-panel
 * edit, not a code deploy.
 */
class ConditionTemplate extends Model
{
    protected $fillable = [
        'name',
        'emirate',
        'contract_format',
    ];

    protected $casts = [
        'emirate' => Emirate::class,
    ];

    /**
     * @return HasMany<ConditionTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConditionTemplateItem::class)->orderBy('sort_order');
    }
}
