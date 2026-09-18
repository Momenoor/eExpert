<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class LeasePrintTemplatePage extends Model
{
    protected $fillable = [
        'lease_print_template_id',
        'page_number',
        'background_image_path',
        'image_width',
        'image_height',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'image_width' => 'integer',
        'image_height' => 'integer',
    ];

    /**
     * @return BelongsTo<LeasePrintTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LeasePrintTemplate::class, 'lease_print_template_id');
    }

    /**
     * @return HasMany<LeasePrintTemplateField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(LeasePrintTemplateField::class);
    }

    public function imageUrl(): ?string
    {
        $path = $this->getAttribute('background_image_path');

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
