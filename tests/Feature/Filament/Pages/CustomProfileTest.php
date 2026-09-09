<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\Auth\CustomProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CustomProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_a_user_can_upload_an_avatar_from_their_own_profile_page(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CustomProfile::class)
            ->fillForm([
                'profile_photo_path' => UploadedFile::fake()->image('me.jpg'),
                'display_name' => $user->name,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
        $this->assertSame(Storage::disk('public')->url($user->profile_photo_path), $user->getFilamentAvatarUrl());
    }
}
