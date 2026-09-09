<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackUserLastSeenTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_panel_stamps_last_seen_at(): void
    {
        $user = User::factory()->create(['last_seen_at' => null]);
        $this->actingAs($user);

        $this->get('/admin')->assertSuccessful();

        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_a_recent_stamp_is_not_immediately_overwritten(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subSeconds(5)]);
        $stamped = $user->last_seen_at;
        $this->actingAs($user);

        $this->get('/admin');

        $this->assertTrue($user->fresh()->last_seen_at->equalTo($stamped));
    }

    public function test_a_stale_stamp_is_refreshed(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subMinutes(5)]);
        $this->actingAs($user);

        $this->get('/admin');

        $this->assertTrue($user->fresh()->last_seen_at->gt(now()->subMinute()));
    }

    public function test_guests_do_not_error_the_middleware(): void
    {
        $this->get('/admin')->assertRedirect();
    }
}
