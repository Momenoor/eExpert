<?php

namespace App\Models;

use App\Enums\PMS\Emirate;
use App\Enums\PMS\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Property extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'emirate',
        'address',
        'municipality',
        'suburb',
        'area',
        'title_deed_number',
        'title_deed_date',
        'plot_number',
        'property_type',
        'property_number',
        'total_units',
        'year_built',
    ];

    protected $casts = [
        'emirate' => Emirate::class,
        'property_type' => PropertyType::class,
        'title_deed_date' => 'date',
        'total_units' => 'integer',
        'year_built' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * The parties (in the 'owner' role) holding a stake in this property.
     *
     * @return BelongsToMany<Party, $this>
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'owner_property', 'property_id', 'owner_party_id')
            ->withPivot('ownership_percentage')
            ->withTimestamps();
    }

    /**
     * What a contract's "Landlord" line should show.
     *
     * When every owner on this property belongs to the same `OwnerGroup` —
     * heirs administering an inherited property as one estate, typically —
     * the group's collective name ("Legal Heirs of Mahmoud Kalbat") stands in
     * for listing each of them individually. Anything less uniform (a mix of
     * groups, or owners with no group at all) falls back to their own names.
     */
    public function landlordName(): string
    {
        $owners = $this->owners()->with('ownerProfile.ownerGroup')->get();

        if ($owners->isEmpty()) {
            return '';
        }

        $groupIds = $owners
            ->map(fn (Party $owner): ?int => $owner->ownerProfile?->getAttribute('owner_group_id'))
            ->unique();

        if ($groupIds->count() === 1 && $groupIds->first() !== null) {
            $group = $owners->first()?->ownerProfile?->getAttribute('ownerGroup');

            if ($group !== null) {
                return $group->name;
            }
        }

        return $owners->pluck('name')->implode(', ');
    }
}
