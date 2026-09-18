<?php

namespace App\Models;

use App\Enums\PMS\ConditionSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionTemplateItem extends Model
{
    protected $fillable = [
        'condition_template_id',
        'section',
        'sort_order',
        'text_en',
        'text_ar',
    ];

    protected $casts = [
        'section' => ConditionSection::class,
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<ConditionTemplate, $this>
     */
    public function conditionTemplate(): BelongsTo
    {
        return $this->belongsTo(ConditionTemplate::class);
    }
}
