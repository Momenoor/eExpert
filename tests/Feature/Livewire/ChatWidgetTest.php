<?php

namespace Tests\Feature\Livewire;

use App\Events\ChatMessageSent;
use App\Livewire\ChatWidget;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ChatWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
        $this->colleague = User::factory()->create();
        $this->actingAs($this->me);
    }

    public function test_starting_a_conversation_creates_it_between_exactly_those_two_users(): void
    {
        Livewire::test(ChatWidget::class)
            ->call('startConversationWith', $this->colleague->id)
            ->assertSet('activeConversationId', fn ($id) => $id !== null);

        $conversation = ChatConversation::sole();

        $this->assertTrue($conversation->participants->pluck('id')->contains($this->me->id));
        $this->assertTrue($conversation->participants->pluck('id')->contains($this->colleague->id));
        $this->assertCount(2, $conversation->participants);
    }

    public function test_starting_a_conversation_twice_reuses_the_same_conversation(): void
    {
        $first = Livewire::test(ChatWidget::class)
            ->call('startConversationWith', $this->colleague->id)
            ->get('activeConversationId');

        $second = Livewire::test(ChatWidget::class)
            ->call('startConversationWith', $this->colleague->id)
            ->get('activeConversationId');

        $this->assertSame($first, $second);
        $this->assertSame(1, ChatConversation::count());
    }

    public function test_sending_a_message_persists_it_and_broadcasts_it(): void
    {
        Event::fake([ChatMessageSent::class]);

        Livewire::test(ChatWidget::class)
            ->call('startConversationWith', $this->colleague->id)
            ->set('body', 'Hello there')
            ->call('sendMessage')
            ->assertSet('body', '');

        $message = ChatMessage::sole();

        $this->assertSame('Hello there', $message->body);
        $this->assertSame($this->me->id, $message->user_id);

        Event::assertDispatched(ChatMessageSent::class, fn ($event) => $event->message->id === $message->id);
    }

    public function test_the_broadcast_goes_to_every_participants_personal_channel(): void
    {
        $conversation = ChatConversation::betweenUsers($this->me, $this->colleague);
        $message = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => $this->me->id,
            'body' => 'hi',
        ]);

        $channels = collect((new ChatMessageSent($message->load('sender')))->broadcastOn())
            ->map(fn (PrivateChannel $channel) => (string) $channel)
            ->all();

        $this->assertContains('private-App.Models.User.'.$this->me->id, $channels);
        $this->assertContains('private-App.Models.User.'.$this->colleague->id, $channels);
    }

    public function test_a_blank_message_is_not_sent(): void
    {
        Livewire::test(ChatWidget::class)
            ->call('startConversationWith', $this->colleague->id)
            ->set('body', '   ')
            ->call('sendMessage');

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_a_user_cannot_read_a_conversation_they_are_not_part_of(): void
    {
        $stranger = User::factory()->create();
        $conversation = ChatConversation::betweenUsers($this->colleague, $stranger);
        ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => $this->colleague->id,
            'body' => 'private to the other two',
        ]);

        $component = Livewire::test(ChatWidget::class)
            ->call('selectConversation', $conversation->id);

        // Selecting an id doesn't authorize it — activeConversation and
        // messages are always derived from the current user's own
        // conversations relationship, so a conversation you don't belong to
        // resolves to null/empty rather than leaking its content.
        $this->assertNull($component->instance()->activeConversation);
        $this->assertCount(0, $component->instance()->messages);
    }

    public function test_toggle_open_flips_the_popup_state(): void
    {
        Livewire::test(ChatWidget::class, ['mode' => 'popup'])
            ->assertSet('isOpen', false)
            ->call('toggleOpen')
            ->assertSet('isOpen', true)
            ->call('toggleOpen')
            ->assertSet('isOpen', false);
    }

    public function test_back_to_list_clears_the_active_conversation(): void
    {
        Livewire::test(ChatWidget::class)
            ->call('startConversationWith', $this->colleague->id)
            ->assertSet('activeConversationId', fn ($id) => $id !== null)
            ->call('backToList')
            ->assertSet('activeConversationId', null);
    }

    public function test_a_user_online_within_the_last_minute_is_reported_online(): void
    {
        $this->colleague->forceFill(['last_seen_at' => now()->subSeconds(10)])->save();
        $this->assertTrue($this->colleague->isOnline());

        $this->colleague->forceFill(['last_seen_at' => now()->subMinutes(5)])->save();
        $this->assertFalse($this->colleague->fresh()->isOnline());
    }
}
