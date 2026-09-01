<?php

namespace App\Models;

use App\Enums\MatterCollectionStatus;
use App\Enums\MatterCommissiong;
use App\Enums\MatterDifficulty;
use App\Enums\MatterLevel;
use App\Enums\MatterStatus;
use App\Observers\MatterObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property mixed $status
 */
#[ObservedBy(MatterObserver::class)]
class Matter extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected array $_cache = [];

    protected $fillable = [
        'year',
        'number',
        'commissioning',
        'distributed_at',
        'next_session_date',
        'initial_report_at',
        'final_report_at',
        'court_id',
        'type_id',
        'level',
        'difficulty',
        'collection_status',
        'review_count',
        'has_substantive_changes',
        'has_court_penalty',
        'final_report_memo_date',
        'is_office_work',
        'received_at',
        'custom_fields',
    ];

    protected array $dates = [
        'distributed_at',
        'next_session_date',
        'initial_report_at',
        'final_report_at',
        'received_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'difficulty' => MatterDifficulty::class,
        'level' => MatterLevel::class,
        'collection_status' => MatterCollectionStatus::class,
        'commissioning' => MatterCommissiong::class,
        'distributed_at' => 'datetime',
        'initial_report_at' => 'datetime',
        'final_report_at' => 'datetime',
        'received_at' => 'datetime',
        'next_session_date' => 'datetime',
        'review_count' => 'integer',
        'has_substantive_changes' => 'boolean',
        'has_court_penalty' => 'boolean',
        'final_report_memo_date' => 'date',
        'is_office_work' => 'boolean',
        'created_at' => 'date:Y-m-d',
        'custom_fields' => 'json',
    ];

    public $timestamps = true;

    protected static function booted()
    {
        static::saved(function (Matter $matter) {
            if ($matter->isDirty('custom_fields') || $matter->wasRecentlyCreated) {
                $customFields = $matter->custom_fields ?? [];

                // Get current meta field names to identify what to remove
                $existingFieldNames = $matter->metas()->pluck('field_name')->toArray();
                $newFieldNames = array_keys($customFields);

                // Remove metas that are no longer in custom_fields
                $fieldsToRemove = array_diff($existingFieldNames, $newFieldNames);
                if (! empty($fieldsToRemove)) {
                    $matter->metas()->whereIn('field_name', $fieldsToRemove)->delete();
                }

                // Update or create metas from custom_fields
                foreach ($customFields as $key => $value) {
                    $matter->metas()->updateOrCreate(
                        ['field_name' => $key],
                        ['field_value' => is_array($value) ? json_encode($value) : $value]
                    );
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    public function children(): HasMany
    {
        return $this->hasMany(Matter::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Matter::class, 'parent_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * All matter_party rows (including representatives).
     */
    public function matterParties(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'matter_id');
    }

    /**
     * Top-level matter_party rows only (excludes representatives).
     * Used by the outer Filament Repeater.
     */
    public function mainParties(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'matter_id')
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0));
    }

    /**
     * Top-level matter_party rows with role 'party' only.
     */
    public function mainPartiesOnly(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'matter_id')
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
            ->where('role', 'party');
    }

    /**
     * Top-level matter_party rows with role 'expert' only.
     */
    public function expertsOnly(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'matter_id')
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
            ->where('role', 'expert');
    }

    public function assistantsOnly(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'matter_id')
            ->where(['role' => 'expert', 'type' => 'assistant']);
    }

    public function isOfficeWork(): bool
    {
        return $this->is_office_work;
    }

    // -----------------------------------------------------------------------
    // FOR QUERYING / READING
    // BelongsToMany gives you Party models directly, useful outside forms.
    // e.g. $matter->parties, $matter->mainPartiesQuery
    // -----------------------------------------------------------------------

    /**
     * All parties in this matter via belongsToMany.
     * e.g. $matter->parties->pluck('name')
     */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'matter_party')
            ->withPivot('id', 'type', 'role', 'parent_id');
    }

    public function mainExpertsOnly(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'matter_party')
            // Filter the related Party record's JSON column
            ->whereJsonContains('parties.role', [
                'role' => 'expert',
                'type' => 'certified',
            ])
            // Filter the relationship link (Pivot Table)
            ->withPivot('id', 'type', 'role as pivot_role', 'parent_id');
    }

    /**
     * Top-level parties only via belongsToMany (no representatives).
     */
    public function mainPartiesQuery(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'matter_party')
            ->whereJsonContains('parties.role', ['role' => 'party'])
            ->withPivot('id', 'type', 'role', 'parent_id')
            ->wherePivotIn('parent_id', [null, '', 0]);
    }

    /**
     * Parties (role = 'party') grouped by type with per-type indexing.
     * e.g. Plaintiff #1, Plaintiff #2, Defendant #1, Defendant #2 ...
     *
     * Used by RepeatableEntry::make('indexedParties') in the infolist.
     */
    // Add this at the top of the class — a plain PHP property, invisible to Eloquent

    public function getIndexedPartiesAttribute()
    {
        if (isset($this->_cache['indexedParties'])) {
            return $this->_cache['indexedParties'];
        }

        $relation = $this->relationLoaded('mainPartiesOnly')
            ? $this->mainPartiesOnly
            : $this->mainPartiesOnly()->with('party', 'representatives.party')->get();

        $result = $relation
            ->groupBy('type')
            ->flatMap(function ($group) {
                return $group->values()->map(function ($mp, $i) {
                    $mp->role_index = $i + 1;

                    return $mp;
                });
            });

        $this->_cache['indexedParties'] = $result;

        return $result;
    }

    public function getIndexedExpertsAttribute()
    {
        if (isset($this->_cache['indexedExperts'])) {
            return $this->_cache['indexedExperts'];
        }

        $relation = $this->relationLoaded('expertsOnly')
            ? $this->expertsOnly
            : $this->expertsOnly()->with('party')->get();

        $result = $relation
            ->groupBy('type')
            ->flatMap(function ($group) {
                return $group->values()->map(function ($mp, $i) {
                    $mp->role_index = $i + 1;

                    return $mp;
                });
            });

        $this->_cache['indexedExperts'] = $result;

        return $result;
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * Update the matter's collection status based on its fees.
     */
    public function updateCollectionStatus(): void
    {
        $fees = $this->fees()->get();

        if ($fees->isEmpty()) {
            $this->collection_status = MatterCollectionStatus::NO_FEES;
        } else {
            $totalAmount = (float) $fees->sum(fn ($fee) => abs($fee->amount));

            $totalAllocated = (float) $this->allocations->sum(fn ($allocation) => abs($allocation->amount));

            if ($totalAllocated <= 0) {
                $this->collection_status = MatterCollectionStatus::UNPAID;
            } elseif ($totalAllocated < $totalAmount) {
                $this->collection_status = MatterCollectionStatus::PARTIAL;
            } else {
                $this->collection_status = MatterCollectionStatus::PAID;
            }
        }

        if ($this->isDirty('collection_status')) {
            $this->saveQuietly();
        }
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function metas(): HasMany
    {
        return $this->hasMany(MatterMeta::class);
    }

    public function status(): Attribute
    {
        return new Attribute(
            get: function () {
                if (! $this->initial_report_at) {
                    return MatterStatus::IN_PROGRESS;
                }
                if (! $this->final_report_at) {
                    return MatterStatus::INITIALED;
                }

                return MatterStatus::FINALIZED;
            }
        );
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function finalReportSubmission(): void
    {
        $this->final_report_at = now();
        if ($this->initial_report_at === null) {
            $this->initial_report_at = now();
        }
        if ($this->final_report_memo_date === null) {
            $this->final_report_memo_date = now();
        }
        $this->save();
    }

    public function incentiveLines(): HasMany
    {
        return $this->hasMany(IncentiveLine::class);
    }

    public function getReference(): string
    {
        return "{$this->number}/{$this->year}";
    }

    public function getReferenceAttribute(): string
    {
        return $this->getReference();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MatterRequest::class);
    }

    public function hasFinalReport(): bool
    {
        return $this->final_report_at !== null;
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function bulkCalendarEvents(): BelongsToMany
    {
        return $this->belongsToMany(CalendarEvent::class, 'calendar_event_matter');
    }
}
