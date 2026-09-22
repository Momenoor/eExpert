<?php

namespace App\Models;

use App\Enums\PMS\PrintDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One document's set of background-image pages and the field positions
 * placed on them — see `LeasePrintFieldResolver` for what each field's
 * value actually resolves to, and the `PrintTemplatePageBuilder` Livewire
 * component for how the office places fields visually.
 *
 * A `LEASE_CONTRACT` template is scoped by `contract_format` (one per
 * government form, shared by every landlord). The other two document
 * types are scoped by `owner_group_id` instead — each estate's own Tax
 * Invoice/Receivable Receipt letterhead. `contract_format` stays unique
 * and not-null either way; a group-scoped template's is a synthetic slug
 * (`syntheticFormatFor()`) rather than a real contract format.
 */
class LeasePrintTemplate extends Model
{
    protected $fillable = [
        'name',
        'contract_format',
        'document_type',
        'owner_group_id',
    ];

    protected $casts = [
        'document_type' => PrintDocumentType::class,
    ];

    /**
     * @return HasMany<LeasePrintTemplatePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(LeasePrintTemplatePage::class)->orderBy('page_number');
    }

    /**
     * @return BelongsTo<OwnerGroup, $this>
     */
    public function ownerGroup(): BelongsTo
    {
        return $this->belongsTo(OwnerGroup::class);
    }

    public static function syntheticFormatFor(int $ownerGroupId, PrintDocumentType $documentType): string
    {
        return "owner_group_{$ownerGroupId}_{$documentType->value}";
    }

    public static function forOwnerGroup(OwnerGroup $group, PrintDocumentType $documentType): ?self
    {
        return static::where('owner_group_id', $group->getKey())
            ->where('document_type', $documentType->value)
            ->first();
    }
}
