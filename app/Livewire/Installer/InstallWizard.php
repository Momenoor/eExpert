<?php

namespace App\Livewire\Installer;

use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvironmentFileWriter;
use App\Services\Installer\InstallationStatus;
use App\Services\Installer\PackageInstaller;
use App\Services\Installer\ServerRequirementsChecker;
use Database\Seeders\AllPermissionsSeeder;
use Database\Seeders\CalendarEventPermissionsSeeder;
use Database\Seeders\IncentiveCalculationPermissionsSeeder;
use Database\Seeders\MatterPermissionsSeeder;
use Database\Seeders\PayrollModulePermissionsSeeder;
use Database\Seeders\PMSConditionTemplatesSeeder;
use Database\Seeders\PMSPermissionsSeeder;
use Database\Seeders\PMSPrintTemplatesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * The seven-step first-run wizard: requirements, database, application
 * details, modules, install (migrate/seed with live progress), admin
 * account, done.
 *
 * Deliberately NOT a Filament page. Filament's panel boots against an
 * authenticated user and a working database; neither exists yet at the point
 * this component has to run, so it is a plain full-page Livewire component with
 * its own minimal layout instead.
 *
 * This is only reachable once Laravel itself can boot (Composer's autoloader
 * and a working `.env` already exist) — a from-scratch deployment missing
 * either of those is instead caught by `public/preinstall.php`, a small
 * framework-free script that installs Composer/npm dependencies, writes a
 * fresh `.env`, and hands off to this wizard's route once it can run.
 */
#[Layout('installer.layout')]
class InstallWizard extends Component
{
    public int $step = 1;

    // Step 2 — database
    public string $db_connection = 'mysql';

    public string $db_host = '127.0.0.1';

    public string $db_port = '3306';

    public string $db_database = '';

    public string $db_username = '';

    public string $db_password = '';

    public ?bool $connectionTested = null;

    public string $connectionMessage = '';

    // Step 3 — application
    public string $app_name = '';

    public string $app_url = '';

    // Step 4 — modules
    public bool $module_pms = true;

    public bool $module_mms = true;

    public bool $module_mms_payroll = true;

    public bool $module_mms_communications = true;

    public bool $module_mms_calendar = true;

    // Step 5 — install (migrate & seed), staged for a live progress bar
    /**
     * @var list<string>
     */
    public array $completedInstallTasks = [];

    public bool $migrated = false;

    public string $migrationOutput = '';

    public bool $migrationFailed = false;

    // Step 6 — admin account
    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    public function mount(): void
    {
        // The route is already behind RedirectIfInstalled; this is only a
        // second line of defence against someone reaching the component by a
        // path that skipped the middleware.
        if (app(InstallationStatus::class)->isInstalled()) {
            $this->redirect('/', navigate: false);

            return;
        }

        $this->app_name = config('app.name', 'Laravel');
        $this->app_url = config('app.url', 'http://localhost');
    }

    /**
     * @return array<int, array{label: string, ok: bool, critical: bool, detail: string}>
     */
    public function getRequirementChecksProperty(): array
    {
        return app(ServerRequirementsChecker::class)->check();
    }

    public function continueFromRequirements(): void
    {
        if (! app(ServerRequirementsChecker::class)->passesCriticalChecks()) {
            $this->addError('requirements', __('One or more required checks are still failing.'));

            return;
        }

        $this->step = 2;
    }

    /**
     * Reset the "tested" flag whenever a connection field changes — a result
     * from before the last edit is not a result for what is on screen now.
     */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'db_')) {
            $this->connectionTested = null;
            $this->connectionMessage = '';
        }
    }

    public function testConnection(): void
    {
        $this->validateDatabaseFields();

        $result = app(DatabaseConnectionTester::class)->test($this->databaseConfig());

        $this->connectionTested = $result['ok'];
        $this->connectionMessage = $result['message'];
    }

    public function saveDatabaseAndContinue(): void
    {
        $this->validateDatabaseFields();

        if ($this->connectionTested !== true) {
            $this->addError('db_database', __('Test the connection before continuing.'));

            return;
        }

        $config = $this->databaseConfig();

        app(EnvironmentFileWriter::class)->set($this->envValuesForDatabase($config));

        // Applied immediately so the migration step later in this same request
        // uses the new connection, rather than whatever was cached at boot.
        $this->applyDatabaseConfigAtRuntime($config);

        // A previously cached config.php would otherwise keep pointing at the
        // old (or absent) database — the exact class of bug that has already
        // destroyed this project's database once by silently ignoring a fresh
        // .env. Clearing it here, before anything touches the database, is
        // cheap insurance.
        Artisan::call('config:clear');

        $this->step = 3;
    }

    public function saveAppSettingsAndContinue(): void
    {
        $this->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
        ]);

        app(EnvironmentFileWriter::class)->set([
            'APP_NAME' => $this->app_name,
            'APP_URL' => $this->app_url,
        ]);

        config(['app.name' => $this->app_name, 'app.url' => $this->app_url]);

        $this->step = 4;
    }

    /**
     * @return array<int, array{key: string, label: string, ok: bool, available: bool}>
     */
    public function getPackageChecksProperty(): array
    {
        $packages = app(PackageInstaller::class);

        return [
            [
                'key' => 'composer',
                'label' => __('Composer dependencies'),
                'ok' => $packages->vendorInstalled(),
                'available' => $packages->composerAvailable(),
            ],
            [
                'key' => 'npm',
                'label' => __('Frontend build'),
                'ok' => $packages->frontendBuilt(),
                'available' => $packages->npmAvailable(),
            ],
        ];
    }

    /**
     * Runs `composer install` / `npm run build` right from the requirements
     * step when the binaries are actually available — the wizard already
     * booted, so this can only ever be re-installing/re-building, never the
     * first-ever install of Composer's own autoloader (that part happens in
     * `public/preinstall.php`, before this component can exist at all).
     */
    public function runPackageCommand(string $key): void
    {
        $packages = app(PackageInstaller::class);

        $result = match ($key) {
            'composer' => $packages->runComposerInstall(),
            'npm' => $packages->runNpmBuild(),
            default => ['ok' => false, 'output' => ''],
        };

        if (! $result['ok']) {
            $this->addError('requirements', $result['output'] !== '' ? $result['output'] : __('Command failed.'));
        }
    }

    public function saveModulesAndContinue(): void
    {
        app(EnvironmentFileWriter::class)->set([
            'MODULE_PMS_ENABLED' => $this->module_pms ? 'true' : 'false',
            'MODULE_MMS_ENABLED' => $this->module_mms ? 'true' : 'false',
            'MODULE_MMS_PAYROLL_ENABLED' => $this->module_mms_payroll ? 'true' : 'false',
            'MODULE_MMS_COMMUNICATIONS_ENABLED' => $this->module_mms_communications ? 'true' : 'false',
            'MODULE_MMS_CALENDAR_ENABLED' => $this->module_mms_calendar ? 'true' : 'false',
        ]);

        config([
            'modules.pms' => $this->module_pms,
            'modules.mms' => $this->module_mms,
            'modules.mms_payroll' => $this->module_mms_payroll,
            'modules.mms_communications' => $this->module_mms_communications,
            'modules.mms_calendar' => $this->module_mms_calendar,
        ]);

        $this->step = 5;
    }

    /**
     * @return array<string, string> [task key => label], in run order —
     *                               drives both the progress bar and
     *                               `runNextInstallTask()`'s work list.
     */
    public function installTasks(): array
    {
        $tasks = [
            'database' => __('Preparing the database'),
            'migrate' => __('Running migrations'),
            'seed_core' => __('Seeding core permissions'),
        ];

        if ($this->module_mms_payroll) {
            $tasks['seed_payroll'] = __('Seeding payroll permissions');
        }

        if ($this->module_mms_calendar) {
            $tasks['seed_calendar'] = __('Seeding calendar permissions');
        }

        if ($this->module_pms) {
            $tasks['seed_pms'] = __('Seeding PMS data');
        }

        $tasks['cache_clear'] = __('Clearing caches');

        return $tasks;
    }

    public function getInstallProgressProperty(): int
    {
        $total = count($this->installTasks());

        return $total === 0 ? 0 : (int) round((count($this->completedInstallTasks) / $total) * 100);
    }

    /**
     * Runs exactly one pending task per call — the Blade view chains calls
     * to this one after another (see `installer.wizard`'s Alpine glue) so
     * the progress bar visibly advances step by step across a few quick
     * requests, with no queue worker involved.
     */
    public function runNextInstallTask(): void
    {
        if ($this->migrationFailed || $this->migrated) {
            return;
        }

        $tasks = array_keys($this->installTasks());
        $index = count($this->completedInstallTasks);

        if ($index >= count($tasks)) {
            $this->migrated = true;

            return;
        }

        $task = $tasks[$index];

        try {
            $this->migrationOutput .= $this->runInstallTask($task);
            $this->completedInstallTasks[] = $task;

            if (count($this->completedInstallTasks) >= count($tasks)) {
                $this->migrated = true;
            }
        } catch (Throwable $e) {
            $this->migrationFailed = true;
            $this->migrationOutput .= $e->getMessage()."\n";
        }
    }

    private function runInstallTask(string $task): string
    {
        return match ($task) {
            'database' => $this->prepareDatabase(),
            'migrate' => $this->runArtisan('migrate', ['--force' => true]),
            'seed_core' => $this->seed([AllPermissionsSeeder::class, MatterPermissionsSeeder::class]),
            'seed_payroll' => $this->seed([PayrollModulePermissionsSeeder::class, IncentiveCalculationPermissionsSeeder::class]),
            'seed_calendar' => $this->seed([CalendarEventPermissionsSeeder::class]),
            'seed_pms' => $this->seed([PMSPermissionsSeeder::class, PMSConditionTemplatesSeeder::class, PMSPrintTemplatesSeeder::class]),
            'cache_clear' => $this->clearCaches(),
            default => '',
        };
    }

    /**
     * Idempotent — `saveDatabaseAndContinue()` already got this far via
     * `DatabaseConnectionTester`'s own create-if-missing handling, so this
     * mainly covers a database dropped between then and now. Reuses that
     * same tester rather than connecting directly: a MySQL connection
     * whose DSN already names a database that doesn't exist fails to
     * connect at all, before any `CREATE DATABASE` statement could run on
     * it — only a connection made without a database name selected can
     * issue that statement, which is exactly what the tester already does.
     */
    private function prepareDatabase(): string
    {
        $result = app(DatabaseConnectionTester::class)->test($this->databaseConfig());

        if (! $result['ok']) {
            throw new RuntimeException($result['message']);
        }

        return $result['message']."\n";
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function runArtisan(string $command, array $arguments = []): string
    {
        Artisan::call($command, $arguments);

        return Artisan::output();
    }

    /**
     * @param  list<class-string>  $seeders
     */
    private function seed(array $seeders): string
    {
        $output = '';

        foreach ($seeders as $seeder) {
            $output .= $this->runArtisan('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        return $output;
    }

    private function clearCaches(): string
    {
        return $this->runArtisan('config:clear').$this->runArtisan('route:clear').$this->runArtisan('view:clear');
    }

    public function continueFromMigration(): void
    {
        if (! $this->migrated) {
            return;
        }

        $this->step = 6;
    }

    public function createAdmin(): void
    {
        $this->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $this->admin_name,
            'email' => $this->admin_email,
            'password' => Hash::make($this->admin_password),
            'email_verified_at' => now(),
        ]);

        // The role Shield treats as the super-admin, read from its own config
        // rather than hardcoded, so this keeps working whatever that role ends
        // up named.
        $roleName = config('filament-shield.super_admin.name', 'super_admin');

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->step = 7;
    }

    public function finish(): void
    {
        app(InstallationStatus::class)->markInstalled();

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->redirect('/mms/login', navigate: false);
    }

    private function validateDatabaseFields(): void
    {
        $this->validate([
            'db_connection' => ['required', 'in:mysql,sqlite'],
            'db_database' => ['required', 'string'],
            'db_host' => ['required_if:db_connection,mysql', 'nullable', 'string'],
            'db_port' => ['required_if:db_connection,mysql', 'nullable', 'string'],
            'db_username' => ['nullable', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array{connection: string, host: string, port: string, database: string, username: string, password: string}
     */
    private function databaseConfig(): array
    {
        return [
            'connection' => $this->db_connection,
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_database,
            'username' => $this->db_username,
            'password' => $this->db_password,
        ];
    }

    /**
     * @param  array{connection: string, host: string, port: string, database: string, username: string, password: string}  $config
     * @return array<string, string>
     */
    private function envValuesForDatabase(array $config): array
    {
        if ($config['connection'] === 'sqlite') {
            return [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $config['database'],
            ];
        }

        return [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config['host'],
            'DB_PORT' => $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
        ];
    }

    /**
     * @param  array{connection: string, host: string, port: string, database: string, username: string, password: string}  $config
     */
    private function applyDatabaseConfigAtRuntime(array $config): void
    {
        $connectionName = $config['connection'];

        if ($connectionName === 'sqlite') {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $config['database'],
            ]);
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.host' => $config['host'],
                'database.connections.mysql.port' => $config['port'],
                'database.connections.mysql.database' => $config['database'],
                'database.connections.mysql.username' => $config['username'],
                'database.connections.mysql.password' => $config['password'],
            ]);
        }

        DB::purge($connectionName);
        DB::setDefaultConnection($connectionName);
    }

    public function render()
    {
        return view('installer.wizard');
    }
}
