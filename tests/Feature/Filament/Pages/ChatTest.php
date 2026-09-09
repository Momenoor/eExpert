<?php

namespace Tests\Feature\Filament\Pages;

use App\Events\ChatMessageSent;
use App\Filament\Pages\Chat;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ChatTest extends TestCase
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

        Filament::setCurrentPanel('admin');
    }

    public function test_any_authenticated_user_can_render_the_chat_page(): void
    {
        $this->get(Chat::getUrl())->assertSuccessful();
    }

    public function test_guest_cannot_access_the_chat_page(): void
    {
        auth()->logout();

        $this->get(Chat::getUrl())->assertRedirect();
    }

    public function test_starting_a_conversation_creates_it_between_exactly_those_two_users(): void
    {
        Livewire::test(Chat::class)
            ->call('startConversationWith', $this->colleague->id)
            ->assertSet('activeConversationId', fn ($id) => $id !== null);

        $conversation = ChatConversation::sole();

        $this->assertTrue($conversation->participants->pluck('id')->contains($this->me->id));
        $this->assertTrue($conversation->participants->pluck('id')->contains($this->colleague->id));
        $this->assertCount(2, $conversation->participants);
    }

    public function test_starting_a_conversation_twice_reuses_the_same_conversation(): void
    {
        $first = Livewire::test(Chat::class)
            ->call('startConversationWith', $this->colleague->id)
            ->get('activeConversationId');

        $second = Livewire::test(Chat::class)
            ->call('startConversationWith', $this->colleague->id)
            ->get('activeConversationId');

        $this->assertSame($first, $second);
        $this->assertSame(1, ChatConversation::count());
    }

    public function test_sending_a_message_persists_it_and_broadcasts_it(): void
    {
        Event::fake([ChatMessageSent::class]);

        Livewire::test(Chat::class)
            ->call('startConversationWith', $this->colleague->id)
            ->set('body', 'Hello there')
            ->call('sendMessage')
            ->assertSet('body', '');

        $message = ChatMessage::sole();

        $this->assertSame('Hello there', $message->body);
        $this->assertSame($this->me->id, $message->user_id);

        Event::assertDispatched(ChatMessageSent::class, fn ($event) => $event->message->id === $message->id);
    }

    public function test_a_blank_message_is_not_sent(): void
    {
        Livewire::test(Chat::class)
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

        $component = Livewire::test(Chat::class)
            ->call('selectConversation', $conversation->id);

        // Selecting an id doesn't authorize it — activeConversation and
        // messages are always derived from the current user's own
        // conversations relationship, so a conversation you don't belong to
        // resolves to null/empty rather than leaking its content.
        $this->assertNull($component->instance()->activeConversation);
        $this->assertCount(0, $component->instance()->messages);
    }
}
