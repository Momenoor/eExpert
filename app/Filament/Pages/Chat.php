<?php

namespace App\Filament\Pages;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Internal, real-time 1-on-1 chat between panel users, delivered over Reverb.
 *
 * Every user this app authenticates can reach every other one here — there is
 * no permission gate, the same way there wouldn't be one on an office intercom.
 * A conversation is created lazily the first time two users message each
 * other (see ChatConversation::betweenUsers()) and reused after that.
 */
class Chat extends Page
{
    protected string $view = 'filament.pages.chat';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    protected static ?int $navigationSort = 3;

    public ?int $activeConversationId = null;

    public string $body = '';

    public string $userSearch = '';

    public function getTitle(): string
    {
        return __('Chat');
    }

    public static function getNavigationLabel(): string
    {
        return __('Chat');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Communication');
    }

    /**
     * @return Collection<int, ChatConversation>
     */
    public function getConversationsProperty(): Collection
    {
        return Auth::user()
            ->chatConversations()
            ->with('participants')
            ->latest('last_message_at')
            ->get();
    }

    /**
     * The other person in a 1-on-1 conversation — participants are always
     * loaded in full (self included) so isConversationUnread() can find this
     * user's own pivot row; this is where "self" gets filtered back out for
     * display.
     */
    public function otherParticipant(ChatConversation $conversation): ?User
    {
        return $conversation->participants->firstWhere('id', '!=', Auth::id());
    }

    /**
     * @return Collection<int, User>
     */
    public function getOtherUsersProperty(): Collection
    {
        return User::query()
            ->where('id', '!=', Auth::id())
            ->when($this->userSearch !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('name', 'like', "%{$this->userSearch}%")
                        ->orWhere('display_name', 'like', "%{$this->userSearch}%");
                });
            })
            ->orderBy('display_name')
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getMessagesProperty(): Collection
    {
        // Scoped through activeConversation, not a raw where() on
        // activeConversationId — that property is client-settable (it's what
        // selectConversation()/wire:click write to), so resolving it through
        // $this->conversations (already scoped to the current user's own
        // relationship) is what stops a user requesting an id they don't
        // belong to from reading that conversation's messages.
        if (! $this->activeConversation) {
            return new Collection;
        }

        return ChatMessage::query()
            ->where('chat_conversation_id', $this->activeConversation->id)
            ->with('sender')
            ->orderBy('created_at')
            ->get();
    }

    public function getActiveConversationProperty(): ?ChatConversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return $this->conversations->firstWhere('id', $this->activeConversationId);
    }

    /**
     * One echo-private listener per conversation this user belongs to, so a
     * message landing in any of them updates the sidebar live — not just the
     * one currently open.
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        if (! Auth::check()) {
            return [];
        }

        $listeners = [];

        foreach (Auth::user()->chatConversations()->pluck('chat_conversations.id') as $id) {
            $listeners["echo-private:chat.conversation.{$id},chat.message.sent"] = 'onMessageReceived';
        }

        return $listeners;
    }

    public function onMessageReceived(): void
    {
        // The event payload isn't consumed directly — both properties are
        // computed straight from the database, so simply letting Livewire
        // re-render after this picks up the new row either way.
    }

    public function selectConversation(int $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->markActiveConversationRead();
    }

    public function startConversationWith(int $userId): void
    {
        $other = User::findOrFail($userId);

        $conversation = ChatConversation::betweenUsers(Auth::user(), $other);

        $this->userSearch = '';
        $this->activeConversationId = $conversation->id;
        $this->markActiveConversationRead();
    }

    public function sendMessage(): void
    {
        $body = trim($this->body);

        if ($body === '' || ! $this->activeConversationId) {
            return;
        }

        $conversation = $this->activeConversation;

        if (! $conversation) {
            return;
        }

        $message = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => Auth::id(),
            'body' => Str::limit($body, 5000, ''),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => $message->created_at]);

        broadcast(new ChatMessageSent($message->load('sender')))->toOthers();

        $this->body = '';
    }

    protected function markActiveConversationRead(): void
    {
        $conversation = $this->activeConversation;

        if (! $conversation) {
            return;
        }

        $conversation->participants()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
    }

    public function isConversationUnread(ChatConversation $conversation): bool
    {
        $pivot = $conversation->participants->firstWhere('id', Auth::id())?->pivot;

        if (! $pivot) {
            return false;
        }

        if (! $conversation->last_message_at) {
            return false;
        }

        return ! $pivot->last_read_at || $pivot->last_read_at->lt($conversation->last_message_at);
    }
}
