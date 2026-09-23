<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gates every request once the app IS installed (see InstallerMiddlewareTest
 * for the installer's own gate, which runs first) — a license that's
 * missing, actively invalid, or simply un-checked for longer than the
 * grace period locks the app down behind `/license`.
 */
class EnsureLicenseIsValidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Makes InstallationStatus::isInstalled() true without a lock
        // file, so RedirectToInstaller lets requests through to this
        // middleware in the first place.
        User::factory()->create();

        // EnsureLicenseIsValid is off by default in tests (see its own
        // docblock) — this class exists specifically to test it, so it
        // opts back in for every test here.
        config(['license.force_enforcement_in_tests' => true]);
    }

    public function test_a_request_is_redirected_to_the_license_page_when_no_license_exists(): void
    {
        $this->get('/')->assertRedirect(route('license.show'));
    }

    public function test_a_request_passes_through_with_a_recently_valid_license(): void
    {
        License::factory()->create([
            'status' => 'active',
            'last_valid_at' => now(),
        ]);

        // '/' always redirects into the default panel regardless of license
        // state (see routes/web.php) — passing through this middleware
        // means that redirect ISN'T to the license page.
        $response = $this->get('/')->assertRedirect();
        $this->assertNotSame(route('license.show'), $response->headers->get('Location'));
    }

    public function test_a_request_passes_through_within_the_grace_period_despite_a_stale_check(): void
    {
        config(['license.grace_days' => 7]);

        License::factory()->create([
            'status' => 'active',
            'last_valid_at' => now()->subDays(3),
        ]);

        $response = $this->get('/')->assertRedirect();
        $this->assertNotSame(route('license.show'), $response->headers->get('Location'));
    }

    public function test_a_request_is_redirected_once_the_grace_period_has_elapsed(): void
    {
        config(['license.grace_days' => 7]);

        License::factory()->create([
            'status' => 'active',
            'last_valid_at' => now()->subDays(10),
        ]);

        $this->get('/')->assertRedirect(route('license.show'));
    }

    public function test_a_suspended_license_redirects_even_within_the_grace_period(): void
    {
        License::factory()->create([
            'status' => 'suspended',
            'last_valid_at' => now(),
        ]);

        $this->get('/')->assertRedirect(route('license.show'));
    }

    public function test_an_expired_license_redirects_even_within_the_grace_period(): void
    {
        License::factory()->create([
            'status' => 'active',
            'expires_at' => now()->subDay(),
            'last_valid_at' => now(),
        ]);

        $this->get('/')->assertRedirect(route('license.show'));
    }

    public function test_the_license_page_itself_is_never_redirected(): void
    {
        $this->get(route('license.show'))->assertSuccessful();
    }

    public function test_livewires_own_update_endpoint_is_never_redirected(): void
    {
        $response = $this->post(route('default-livewire.update'), []);

        $this->assertNotSame(302, $response->getStatusCode());
    }
}
