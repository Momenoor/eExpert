<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One contract format's set of background-image pages and the field
 * positions placed on them — see `LeasePrintFieldResolver` for what each
 * field's value actually resolves to, and the `PrintTemplatePageBuilder`
 * Livewire component for how the office places fields visually.
 */
class LeasePrintTemplate extends Model
{
    protected $fillable = [
        'name',
        'contract_format',
    ];

    /**
     * @return HasMany<LeasePrintTemplatePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(LeasePrintTemplatePage::class)->orderBy('page_number');
    }
}
