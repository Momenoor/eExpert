<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use FilamentInbox\Concerns\HasInbox;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read ChatConversationUser|null $pivot Only set when loaded through a chat conversation's participants() relation.
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
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
        'profile_photo_path',
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

    /**
     * @return BelongsToMany<ChatConversation, $this, ChatConversationUser, 'pivot'>
     */
    public function chatConversations(): BelongsToMany
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_conversation_user')
            ->using(ChatConversationUser::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (blank($this->profile_photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    public function phone(): Attribute
    {
        return new Attribute(
            get: fn () => $this->party?->phone ?? '',
        );
    }
}
