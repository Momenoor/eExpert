<?php

namespace App\Livewire;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The real-time 1-on-1 chat between panel users, delivered over broadcasting
 * (Reverb locally, Pusher on shared hosting — see resources/js/echo.js).
 *
 * One component backs two presentations, switched purely at the view layer:
 * `mode="page"` is the full two-pane view embedded in the Chat nav page,
 * `mode="popup"` is the floating bottom-right bubble rendered on every panel
 * page via a render hook. Both share the same state and the same
 * conversation/message data — there's no separate "popup" data model.
 *
 * There is no permission gate: every user this app authenticates can reach
 * every other one here, the same way there wouldn't be one on an office
 * intercom. A conversation is created lazily the first time two users message
 * each other (see ChatConversation::betweenUsers()) and reused after that.
 */
class ChatWidget extends Component
{
    public string $mode = 'page';

    public bool $isOpen = false;

    public ?int $activeConversationId = null;

    public string $body = '';

    public string $userSearch = '';

    public function mount(string $mode = 'page'): void
    {
        $this->mode = $mode;
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

    public function getUnreadCountProperty(): int
    {
        return $this->conversations->filter(fn (ChatConversation $c) => $this->isConversationUnread($c))->count();
    }

    /**
     * A single listener on the current user's own personal channel — the
     * same App.Models.User.{id} channel Laravel's model notifications already
     * broadcast on — rather than one listener per conversation. That channel
     * exists before any conversation does, so it's already subscribed from
     * this component's very first mount; a per-conversation channel would
     * only be added to this list for conversations that already existed the
     * last time this component rendered, which is what made a brand new
     * conversation invisible to its other participant until they refreshed.
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        if (! Auth::check()) {
            return [];
        }

        return [
            'echo-private:App.Models.User.'.Auth::id().',chat.message.sent' => 'onMessageReceived',
        ];
    }

    public function onMessageReceived(): void
    {
        // The event payload isn't consumed directly — both properties are
        // computed straight from the database, so simply letting Livewire
        // re-render after this picks up the new row either way.
    }

    public function toggleOpen(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function backToList(): void
    {
        $this->activeConversationId = null;
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

    /**
     * A stable, correctly-sized avatar URL for any user — the initials
     * fallback is asked for a fixed high-resolution image (2x the largest
     * size this UI ever displays one at) so it stays crisp instead of being
     * upscaled and blurry, and a real uploaded avatar is masked to a circle
     * by the same `object-cover` class every avatar in this widget uses.
     */
    public function avatarUrl(User $user): string
    {
        return $user->getFilamentAvatarUrl()
            ?? 'https://ui-avatars.com/api/?name='.urlencode($user->display_name ?: $user->name).'&size=128&background=2563EB&color=FFFFFF';
    }

    public function render(): View
    {
        return view('livewire.chat-widget');
    }
}
