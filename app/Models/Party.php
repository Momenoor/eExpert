<?php

namespace App\Models;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Party extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $fillable = [
        'name',
        'phone',
        'fax',
        'address',
        'email',
        'role',
        'extra',
        'parent_id',
        'user_id',
        'old_id',
    ];

    protected $casts = [
        'role' => 'array', // This tells Laravel to JSON encode/decode automatically
        'extra' => 'array',
        'phone' => 'array',
        'email' => 'array',
    ];

    public function getRoleAttribute($value)
    {
        $roles = json_decode($value, true) ?? [];

        // If it's already in the form format (nested with 'role', 'type', 'field')
        if (isset($roles['role'])) {
            return $roles;
        }

        // If it's an array of objects (database format), transform it for the form
        $formRole = [
            'role' => [],
            'type' => [],
            'field' => null,
        ];

        foreach ($roles as $item) {
            if (isset($item['role'])) {
                if (! in_array($item['role'], $formRole['role'])) {
                    $formRole['role'][] = $item['role'];
                }
                if ($item['role'] === 'expert') {
                    if (isset($item['type']) && ! in_array($item['type'], $formRole['type'])) {
                        $formRole['type'][] = $item['type'];
                    }
                    if (isset($item['field'])) {
                        $formRole['field'] = $item['field'];
                    }
                }
            }
        }

        return $formRole;
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($party) {
            if (isset($party->role['role']) && is_array($party->role['role'])) {
                $newRoles = [];
                $roles = $party->role['role'];
                $types = $party->role['type'] ?? [];
                $field = $party->role['field'] ?? null;

                foreach ($roles as $role) {
                    if ($role === 'expert') {
                        if (empty($types)) {
                            $newRoles[] = [
                                'role' => 'expert',
                                'field' => $field,
                            ];
                        } else {
                            foreach ($types as $type) {
                                $newRoles[] = [
                                    'role' => 'expert',
                                    'type' => $type,
                                    'field' => $field,
                                ];
                            }
                        }
                    } else {
                        $newRoles[] = ['role' => $role];
                    }
                }
                $party->role = $newRoles;
            }
        });

    }

    /**
     * Parties holding a given role, optionally narrowed to an expert type.
     *
     * `role` is a JSON array of objects, and every report asked about it with
     * `whereJsonContains('role', ['role' => ..., 'type' => ...])`. That is
     * MySQL-only in practice — SQLite renders the same call as a comparison
     * against each element's whole JSON text — which is why none of those pages
     * could be covered by a test. Sql::jsonArrayHas() states the same question
     * in whichever dialect is connected.
     *
     * @param  Builder<Party>  $query
     */
    public function scopeWithRole(Builder $query, string $role, ?string $type = null): void
    {
        $pairs = ['role' => $role];

        if ($type !== null) {
            $pairs['type'] = $type;
        }

        [$sql, $bindings] = Sql::jsonArrayHas('parties.role', $pairs);

        $query->whereRaw($sql, $bindings);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'parent_id');
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(MatterParty::class, 'parent_id', 'party_id');
    }

    public function matters(): Party|HasManyThrough
    {
        return $this->hasManyThrough(Matter::class, MatterParty::class, 'party_id', 'id', 'id', 'matter_id');
    }

    public function incentiveAssistantLines(): HasMany
    {
        return $this->hasMany(IncentiveAssistantLine::class);
    }

    public function incentiveAssistantExtras(): HasMany
    {
        return $this->hasMany(IncentiveAssistantExtra::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(PartyLeave::class);
    }

    public function isExpert(): bool
    {
        // The accessor ensures 'role' is an array with a 'role' key.
        // We check if 'expert' exists inside that subarray.
        return isset($this->role['role']) && in_array('expert', (array) $this->role['role']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
