<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
        $this->get('/admin')->assertRedirect(route('installer.show'));
    }

    public function test_the_installer_itself_is_reachable_when_not_installed(): void
    {
        $this->get(route('installer.show'))->assertSuccessful();
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
