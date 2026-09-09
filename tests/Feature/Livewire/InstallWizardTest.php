<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Installer\InstallWizard;
use App\Models\User;
use App\Services\Installer\EnvironmentFileWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The wizard end to end, including a genuine `migrate --force` + seed against
 * a throwaway SQLite file.
 *
 * That last part is the reason this is a Feature test running its own real
 * migration rather than one more mock: it is the actual verification Phase
 * 3 asked for ("running migrate:fresh on a blank database completes without
 * errors"), exercised through the exact code path an operator would hit,
 * against a file created and deleted by this test alone — never the shared
 * :memory: connection RefreshDatabase already migrated for every other test in
 * this suite, and never a real `.env`.
 */
class InstallWizardTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDbPath;

    private string $tempEnvPath;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDbPath = sys_get_temp_dir().'/install-wizard-test-'.uniqid().'.sqlite';
        $this->tempEnvPath = sys_get_temp_dir().'/install-wizard-test-'.uniqid().'.env';
        $this->lockFile = sys_get_temp_dir().'/install-wizard-test-'.uniqid().'.lock';

        touch($this->tempDbPath);
        File::put($this->tempEnvPath, "APP_NAME=Laravel\nAPP_URL=http://localhost\n");

        // Without this, InstallationStatus::isInstalled() reads the REAL
        // storage/installed lock file this project's own deployment relies on
        // — which exists — and every test in this class would find mount()
        // redirecting away immediately, before the wizard ever renders.
        config(['installer.lock_file' => $this->lockFile]);

        // The wizard resolves this from the container, so binding a writer that
        // targets a throwaway file is enough to guarantee nothing it does can
        // ever touch the project's real .env.
        $this->app->bind(EnvironmentFileWriter::class, fn () => new EnvironmentFileWriter($this->tempEnvPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDbPath);
        @unlink($this->tempEnvPath);
        @unlink($this->lockFile);

        // See InstallationStatusTest::tearDown() — without this the override
        // leaks into every test that runs after this one in the same process.
        config(['installer.lock_file' => storage_path('installed')]);

        // No connection restoration here on purpose: only one test in this
        // class actually repoints the default connection (see the docblock on
        // test_the_full_wizard_installs_a_working_application), and it runs in
        // its own process via #[RunInSeparateProcess] specifically so that
        // process can exit and disappear without this class needing to undo
        // anything for the tests that share the main process. A DB::purge()
        // here unconditionally would run after every test, including ones that
        // never touched the connection, and that is what was destroying the
        // single persistent :memory: connection Laravel's test runner keeps
        // alive for the whole suite.
        parent::tearDown();
    }

    public function test_it_redirects_away_when_already_installed(): void
    {
        User::factory()->create();

        Livewire::test(InstallWizard::class)
            ->assertRedirect('/');
    }

    /**
     * Isolated in its own process for a reason that matters: Laravel's test
     * runner keeps ONE persistent in-memory SQLite connection alive for the
     * whole `php artisan test` run and resets it between tests with a
     * transaction rollback rather than a re-migration. saveDatabaseAndContinue()
     * calls DB::purge('sqlite') as part of doing its real job — repointing the
     * app at a newly-configured database — and against that shared connection,
     * a purge destroys the schema every other test in this process still
     * depends on. Running this one test in a separate process is what lets it
     * exercise the actual production code path without taking the rest of the
     * suite down with it.
     */
    #[RunInSeparateProcess]
    public function test_the_full_wizard_installs_a_working_application(): void
    {
        $component = Livewire::test(InstallWizard::class)
            ->assertSet('step', 1)
            ->call('continueFromRequirements')
            ->assertSet('step', 2)
            ->set('db_connection', 'sqlite')
            ->set('db_database', $this->tempDbPath)
            ->call('testConnection')
            ->assertSet('connectionTested', true)
            ->call('saveDatabaseAndContinue')
            ->assertSet('step', 3)
            ->set('app_name', 'Test Office')
            ->set('app_url', 'https://test.example')
            ->call('saveAppSettingsAndContinue')
            ->assertSet('step', 4);

        // The real migration, against the real (throwaway) file.
        $component->call('runMigrations')
            ->assertSet('migrated', true)
            ->assertSet('migrationFailed', false);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertGreaterThan(0, Permission::count());

        $component->call('continueFromMigration')
            ->assertSet('step', 5)
            ->set('admin_name', 'Test Admin')
            ->set('admin_email', 'admin@test.example')
            ->set('admin_password', 'password123')
            ->set('admin_password_confirmation', 'password123')
            ->call('createAdmin')
            ->assertSet('step', 6);

        $admin = User::where('email', 'admin@test.example')->sole();
        $this->assertTrue($admin->hasRole(config('filament-shield.super_admin.name', 'super_admin')));

        $component->call('finish');

        $this->assertSame('APP_NAME="Test Office"', $this->envLine('APP_NAME'));
    }

    public function test_it_will_not_advance_past_the_database_step_without_a_successful_test(): void
    {
        Livewire::test(InstallWizard::class)
            ->call('continueFromRequirements')
            ->set('db_connection', 'sqlite')
            ->set('db_database', $this->tempDbPath)
            // No testConnection() call — connectionTested is still null.
            ->call('saveDatabaseAndContinue')
            ->assertSet('step', 2)
            ->assertHasErrors(['db_database']);
    }

    public function test_editing_a_database_field_resets_the_tested_flag(): void
    {
        Livewire::test(InstallWizard::class)
            ->call('continueFromRequirements')
            ->set('db_connection', 'sqlite')
            ->set('db_database', $this->tempDbPath)
            ->call('testConnection')
            ->assertSet('connectionTested', true)
            ->set('db_database', $this->tempDbPath.'-different')
            ->assertSet('connectionTested', null);
    }

    public function test_the_admin_account_requires_a_confirmed_password(): void
    {
        Livewire::test(InstallWizard::class)
            ->set('step', 5)
            ->set('admin_name', 'Test Admin')
            ->set('admin_email', 'admin@test.example')
            ->set('admin_password', 'password123')
            ->set('admin_password_confirmation', 'not-the-same')
            ->call('createAdmin')
            ->assertHasErrors(['admin_password'])
            ->assertSet('step', 5);
    }

    public function test_finishing_marks_the_application_as_installed(): void
    {
        Livewire::test(InstallWizard::class)
            ->set('step', 6)
            ->call('finish');

        $this->assertTrue(File::exists($this->lockFile));
    }

    private function envLine(string $key): ?string
    {
        $contents = File::get($this->tempEnvPath);

        foreach (explode("\n", $contents) as $line) {
            if (str_starts_with($line, $key.'=')) {
                return trim($line);
            }
        }

        return null;
    }
}
