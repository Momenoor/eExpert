<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeasePrintTemplateField extends Model
{
    protected $fillable = [
        'lease_print_template_page_id',
        'field_key',
        'x_percent',
        'y_percent',
        'width_percent',
        'height_percent',
        'column_widths',
        'hidden_columns',
        'font_size',
        'text_align',
        'rtl',
        'language',
    ];

    protected $casts = [
        'x_percent' => 'decimal:3',
        'y_percent' => 'decimal:3',
        'width_percent' => 'decimal:3',
        'height_percent' => 'decimal:3',
        'column_widths' => 'array',
        'hidden_columns' => 'array',
        'font_size' => 'integer',
        'rtl' => 'boolean',
    ];

    /**
     * @return BelongsTo<LeasePrintTemplatePage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(LeasePrintTemplatePage::class, 'lease_print_template_page_id');
    }
}
