<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_avatar_has_no_filament_avatar_url(): void
    {
        $user = User::factory()->create(['profile_photo_path' => null]);

        $this->assertNull($user->getFilamentAvatarUrl());
    }

    public function test_profile_photo_path_is_fillable_and_produces_a_public_url(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $file->store('avatars', 'public');

        $user = User::factory()->create(['profile_photo_path' => $path]);

        $this->assertSame($path, $user->profile_photo_path);
        $this->assertSame(Storage::disk('public')->url($path), $user->getFilamentAvatarUrl());
    }

    public function test_filament_default_filesystem_disk_is_public_so_vendor_avatar_fields_display(): void
    {
        // The vendor avatar upload field (TomatoPHP\FilamentUsers) never calls
        // ->disk() itself, so whatever this config resolves to is where an
        // uploaded avatar is both stored and later rendered from. It must be
        // 'public' — the only disk with a servable URL — or uploads succeed
        // but the image never displays.
        $this->assertSame('public', config('filament.default_filesystem_disk'));
    }
}
