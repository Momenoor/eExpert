<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\Chat;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Chat nav page itself is just a thin shell embedding the ChatWidget
 * Livewire component full-screen — its own behavior (starting conversations,
 * sending messages, scoping) is covered by ChatWidgetTest.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

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
}
