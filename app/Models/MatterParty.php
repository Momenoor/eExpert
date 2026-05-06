<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;


class MatterParty extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $table = 'matter_party';

    protected $fillable = [
        'id',
        'matter_id',
        'party_id',
        'parent_id',
        'type',
        'role',
    ];

    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected static function booted(): void
    {
        static::deleting(function (MatterParty $matterParty) {
            $matterParty->representatives()->delete();
        });

        static::creating(function (MatterParty $matterParty) {
            if (!$matterParty->matter_id && $matterParty->parent_id) {
                $parent = MatterParty::find($matterParty->parent_id);
                if ($parent) {
                    $matterParty->matter_id = $parent->matter_id;
                }
            }
        });
    }

    /**
     * The matter this row belongs to.
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function matter_fees(): HasMany
    {
        return $this->hasMany(Fee::class, 'matter_id', 'matter_id');
    }

    public function matter_allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'matter_id', 'matter_id');
    }

    /**
     * The party this row points to.
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * The parent matter party record (the plaintiff/defendant this representative acts for).
     * parent_id → matter_party.id
     */
    public function parentMatterParty(): BelongsTo
    {
        return $this->belongsTo(MatterParty::class, 'parent_id', 'id');
    }

    /**
     * Representatives of this party in the same matter.
     *
     * Finds matter_party rows where:
     *   parent_id = this.id  → refers back to this row
     *   role      = 'representative'
     *
     * Used by the nested Filament Repeater.
     */
    public function representatives(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'parent_id', 'id')
            ->where('role', 'representative');
    }

    public function experts(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id')->whereJsonContains('role' , ['role' => 'expert', 'type' => 'certified']);
    }
}
