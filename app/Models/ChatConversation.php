<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * @return BelongsToMany<User, $this, ChatConversationUser, 'pivot'>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_conversation_user')
            ->using(ChatConversationUser::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * The 1-on-1 conversation between these two users, creating it if it
     * doesn't exist yet. Order-independent: fetching (a, b) or (b, a) always
     * resolves to the same row.
     */
    public static function betweenUsers(User $a, User $b): self
    {
        // Filtered in PHP rather than a HAVING clause on the withCount()
        // subquery alias — MySQL tolerates that without a GROUP BY, SQLite
        // (what the test suite runs on) rejects it outright.
        $existing = self::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $b->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing) {
            return $existing;
        }

        $conversation = self::create();
        $conversation->participants()->attach([$a->id, $b->id]);

        return $conversation;
    }
}
