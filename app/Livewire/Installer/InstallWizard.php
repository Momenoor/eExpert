<?php

namespace App\Livewire\Installer;

use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvironmentFileWriter;
use App\Services\Installer\InstallationStatus;
use App\Services\Installer\ServerRequirementsChecker;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * The six-step first-run wizard: requirements, database, application details,
 * migrate & seed, admin account, done.
 *
 * Deliberately NOT a Filament page. Filament's panel boots against an
 * authenticated user and a working database; neither exists yet at the point
 * this component has to run, so it is a plain full-page Livewire component with
 * its own minimal layout instead.
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

    // Step 4 — migration & seeding
    public bool $migrated = false;

    public string $migrationOutput = '';

    public bool $migrationFailed = false;

    // Step 5 — admin account
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

    public function runMigrations(): void
    {
        $this->migrationFailed = false;

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            Artisan::call('db:seed', ['--class' => ProductionDatabaseSeeder::class, '--force' => true]);
            $output .= Artisan::output();

            $this->migrationOutput = $output;
            $this->migrated = true;
        } catch (Throwable $e) {
            $this->migrationFailed = true;
            $this->migrationOutput = $e->getMessage();
        }
    }

    public function continueFromMigration(): void
    {
        if (! $this->migrated) {
            return;
        }

        $this->step = 5;
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

        $this->step = 6;
    }

    public function finish(): void
    {
        app(InstallationStatus::class)->markInstalled();

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->redirect('/admin/login', navigate: false);
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
