<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    /**
     * One private channel per participant — App.Models.User.{id}, the same
     * personal channel Laravel's own model notifications already use, so no
     * extra Broadcast::channel() authorization was needed — rather than a
     * single per-conversation channel.
     *
     * A per-conversation channel only works for a user already subscribed to
     * it, and that subscription list is baked into a Livewire component's
     * response at render time (see ChatWidget::getListeners()): a user whose
     * chat widget was already on the page when this conversation was first
     * created never had it in that list, so they'd only start receiving
     * messages for it after something else caused their own component to
     * re-render — in practice, only after a manual page refresh. A personal
     * channel exists for every user before any conversation does, so it's
     * always already subscribed.
     *
     * @return array<Channel|PresenceChannel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->message->conversation->participants
            ->map(fn ($user) => new PrivateChannel($user))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->chat_conversation_id,
            'body' => $this->message->body,
            'sender_id' => $sender->id,
            'sender_name' => $sender->display_name ?: $sender->name,
            'sender_avatar' => $sender->getFilamentAvatarUrl(),
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
