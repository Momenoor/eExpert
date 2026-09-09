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
     * @return array<Channel|PresenceChannel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.conversation.{$this->message->chat_conversation_id}"),
        ];
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
