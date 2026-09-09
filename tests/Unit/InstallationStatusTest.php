<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Installer\InstallationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Whether a deployment counts as "installed".
 *
 * The lock file path is pointed at a throwaway location for every test here —
 * never at the real `storage/installed` this project's own deployment relies
 * on. That real file is what stops the installer's global middleware from
 * redirecting every request on an already-running system; a test that deleted
 * or overwrote it, even temporarily, would be testing against the exact switch
 * that protects this application's own traffic.
 */
class InstallationStatusTest extends TestCase
{
    use RefreshDatabase;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lockFile = sys_get_temp_dir().'/installer-status-test-'.uniqid().'.lock';
        config(['installer.lock_file' => $this->lockFile]);
    }

    protected function tearDown(): void
    {
        File::delete($this->lockFile);

        // Without this, the override leaks into every test that runs after
        // this one in the same process — config() mutates the booted app's
        // repository directly and nothing else resets it between tests. That
        // is exactly what broke TypeResourceTest and others: `installer.lock_file`
        // stayed pointed at this test's already-deleted temp path, so the
        // installer middleware saw "no lock file" for the rest of the suite.
        config(['installer.lock_file' => storage_path('installed')]);

        parent::tearDown();
    }

    public function test_it_is_not_installed_with_no_lock_file_and_no_users(): void
    {
        $this->assertFalse(app(InstallationStatus::class)->isInstalled());
    }

    public function test_the_lock_file_alone_is_sufficient(): void
    {
        File::put($this->lockFile, 'installed');

        $this->assertTrue(app(InstallationStatus::class)->isInstalled());
    }

    public function test_an_existing_user_counts_as_installed_even_with_no_lock_file(): void
    {
        User::factory()->create();

        $this->assertFalse(File::exists($this->lockFile), 'the lock file should not exist yet');

        $this->assertTrue(app(InstallationStatus::class)->isInstalled());
    }

    public function test_an_existing_user_backfills_the_lock_file(): void
    {
        User::factory()->create();

        app(InstallationStatus::class)->isInstalled();

        // So the next request never needs the database query at all.
        $this->assertTrue(File::exists($this->lockFile));
    }

    public function test_mark_installed_creates_the_lock_file(): void
    {
        app(InstallationStatus::class)->markInstalled();

        $this->assertTrue(File::exists($this->lockFile));
    }
}
