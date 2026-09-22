<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two-way gate around the installer: every ordinary route redirects to it
 * until the application is installed, and the installer itself redirects away
 * the moment it is.
 *
 * The lock file is pointed at a throwaway path for the whole class (see
 * setUp/tearDown) for the same reason InstallationStatusTest does it — the real
 * `storage/installed` is what keeps this deployment's own traffic from being
 * redirected, and no test should be able to touch it.
 */
class InstallerMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFile = sys_get_temp_dir().'/installer-middleware-test-'.uniqid().'.lock';
        config(['installer.lock_file' => $this->lockFile]);
    }

    protected function tearDown(): void
    {
        File::delete($this->lockFile);

        // See InstallationStatusTest::tearDown() — without this the override
        // leaks into every test that runs after this one in the same process.
        config(['installer.lock_file' => storage_path('installed')]);

        parent::tearDown();
    }

    public function test_an_uninstalled_application_redirects_the_homepage_to_the_installer(): void
    {
        $this->get('/')->assertRedirect(route('installer.show'));
    }

    public function test_an_uninstalled_application_redirects_the_admin_panel_to_the_installer(): void
    {
        // The panel's middleware stack is independent of the app's `web` group,
        // so this is the one that would have missed the guard if it had only
        // been registered in bootstrap/app.php.
        $this->get('/mms')->assertRedirect(route('installer.show'));
    }

    public function test_the_installer_itself_is_reachable_when_not_installed(): void
    {
        $this->get(route('installer.show'))->assertSuccessful();
    }

    /**
     * A freshly-written `.env` defaults SESSION_DRIVER to `database`, but
     * the `sessions` table doesn't exist until the installer's own Install
     * step runs `migrate` — every request before that point, including the
     * installer's own pages, would otherwise fail trying to read/write a
     * table that isn't there yet.
     */
    public function test_the_installer_is_reachable_even_when_the_configured_session_driver_has_no_table_yet(): void
    {
        config(['session.driver' => 'database']);
        Schema::dropIfExists('sessions');

        $this->get(route('installer.show'))->assertSuccessful();
    }

    /**
     * The operator hasn't picked a language yet at this point in the flow
     * — and a freshly-copied `.env` may already default `APP_LOCALE` to
     * this deployment's own default (Arabic) — so every installer page
     * renders in English regardless of what the app's own locale is
     * configured to.
     */
    public function test_the_installer_always_renders_in_english_regardless_of_the_configured_app_locale(): void
    {
        config(['app.locale' => 'ar']);

        $this->get(route('installer.show'));

        $this->assertSame('en', app()->getLocale());
    }

    /**
     * `Livewire::test()` drives components in-process and never touches
     * real HTTP routing, so it can't catch this: every wire:click on the
     * installer's own page fires a real AJAX POST to Livewire's own
     * update endpoint (`livewire-<hash>/update`), whose route name isn't
     * `installer.*`. Without bypassing it here too, that POST gets
     * redirected to `installer.show`, the browser's fetch follows it and
     * gets back a full HTML page instead of Livewire's expected JSON, and
     * every step's Continue button just appears to reload the page
     * instead of advancing.
     */
    /**
     * Same class of bug as the sessions table, one level up: `CACHE_STORE`
     * defaults to `database` too, and Livewire touches the cache on every
     * single request (its own checksum-failure tracking) — not just when
     * the installer's own code happens to call `cache()`.
     */
    public function test_the_cache_store_falls_back_to_file_when_its_own_table_does_not_exist_yet(): void
    {
        config(['cache.default' => 'database']);
        Schema::dropIfExists('cache');

        // The GET request that renders the wizard doesn't happen to read
        // the cache itself — the failure is specifically on a step's own
        // wire:click, which is Livewire's own checksum-failure tracking
        // reading the cache on every POST to its update endpoint. That
        // endpoint needs a real, matched route, which is why this asserts
        // against it directly rather than the installer's own GET page
        // (see the `livewire*` bypass test above for why a guessed route
        // wouldn't actually exercise this middleware at all).
        $this->post(route('default-livewire.update'), []);

        $this->assertSame('file', config('cache.default'));
    }

    public function test_livewires_own_update_endpoint_is_never_redirected(): void
    {
        // Livewire's update route is registered under a hash unique to
        // this install (`livewire-<hash>/update`) — resolved via its own
        // route name rather than guessed, so this test exercises the
        // real, matched route (an unmatched URL 404s before any 'web'
        // group middleware — including this one — ever runs, which would
        // make this test pass regardless of whether the fix is in place).
        $response = $this->post(route('default-livewire.update'), []);

        $this->assertNotSame(302, $response->getStatusCode());
    }

    /**
     * A stale `storage/installed` lock file left over from an earlier
     * attempt — or one `InstallationStatus` auto-writes the moment it
     * ever sees a `users` row — can say "installed" even though the
     * database has since been reset to try the installer again. The
     * `database` session driver still has to give way in that case, or
     * every request just hits the same missing-table error a second time.
     */
    public function test_a_stale_lock_file_does_not_bring_back_the_missing_sessions_table_error(): void
    {
        File::ensureDirectoryExists(dirname($this->lockFile));
        File::put($this->lockFile, now()->toDateTimeString()."\n");
        config(['session.driver' => 'database']);
        Schema::dropIfExists('sessions');

        // Not asserting success here — with the database genuinely reset,
        // homepage/auth logic downstream may still fail on other missing
        // tables. What this covers is that it fails on something OTHER
        // than the sessions table itself, i.e. the session driver override
        // took effect regardless of the (stale) installed flag.
        $this->get('/');

        $this->assertSame('file', config('session.driver'));
    }

    public function test_an_installed_application_does_not_redirect_the_homepage(): void
    {
        User::factory()->create();

        $this->get('/')->assertSuccessful();
    }

    public function test_an_installed_application_redirects_the_installer_away(): void
    {
        User::factory()->create();

        $this->get(route('installer.show'))->assertRedirect('/');
    }

    public function test_asset_and_health_check_routes_are_never_redirected(): void
    {
        // /up is Laravel's own health check route, registered in bootstrap/app.php
        // independently of the installed state — a load balancer polling it
        // during an install should see 200, not a redirect loop.
        $this->get('/up')->assertSuccessful();
    }
}
