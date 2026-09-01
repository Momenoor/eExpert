<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use FilamentInbox\Concerns\HasInbox;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasInbox, HasRoles, Notifiable;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $fillable = [
        'name',
        'password',
        'language',
        'email',
        'display_name',
        'font_size',
        'notify_by_email',
        'notify_by_whatsapp',
    ];

    protected $with = [
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'ui_preferences' => 'array',
        'font_size' => 'integer',
        'notify_by_email' => 'boolean',
        'notify_by_whatsapp' => 'boolean',
    ];

    /**
     * The Party record this user acts as (assistant, expert, etc.).
     *
     * @return HasOne<Party, $this>
     */
    public function party(): HasOne
    {
        return $this->hasOne(Party::class);
    }

    public function incentiveCalculations(): HasMany
    {
        return $this->hasMany(IncentiveCalculation::class, 'created_by');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function phone(): Attribute
    {
        return new Attribute(
            get: fn () => $this->party?->phone ?? '',
        );
    }
}
