<?php

namespace App\Livewire\Installer;

use App\Models\License;
use App\Models\User;
use App\Services\Installer\DatabaseConnectionTester;
use App\Services\Installer\EnvironmentFileWriter;
use App\Services\Installer\InstallationStatus;
use App\Services\Installer\ModulePruner;
use App\Services\Installer\PackageInstaller;
use App\Services\Installer\ServerRequirementsChecker;
use App\Services\License\LicenseClient;
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
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * The nine-step first-run wizard: requirements, license, database,
 * application details, modules, optional integrations (WhatsApp,
 * Microsoft Graph mail), install (migrate/seed with live progress),
 * admin account, done.
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

    // Step 2 — license
    public string $license_key = '';

    public ?bool $licenseActivated = null;

    public string $licenseMessage = '';

    public ?string $licensePlan = null;

    public ?string $licenseExpiresAt = null;

    // Step 3 — database
    public string $db_connection = 'mysql';

    public string $db_host = '127.0.0.1';

    public string $db_port = '3306';

    public string $db_database = '';

    public string $db_username = '';

    public string $db_password = '';

    public ?bool $connectionTested = null;

    public string $connectionMessage = '';

    // Step 4 — application
    public string $app_name = '';

    public string $app_url = '';

    // Step 5 — modules
    public bool $module_pms = true;

    public bool $module_mms = true;

    public bool $module_mms_payroll = true;

    public bool $module_mms_communications = true;

    public bool $module_mms_calendar = true;

    // Step 6 — optional integrations (WhatsApp, Microsoft Graph mail)
    public string $whatsapp_phone_id = '';

    public string $whatsapp_token = '';

    public string $whatsapp_from = '';

    public string $graph_tenant_id = '';

    public string $graph_client_id = '';

    public string $graph_client_secret = '';

    // Step 7 — install (migrate & seed), staged for a live progress bar
    /**
     * @var list<string>
     */
    public array $completedInstallTasks = [];

    public bool $migrated = false;

    public string $migrationOutput = '';

    public bool $migrationFailed = false;

    // Step 8 — admin account
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

        // `public/preinstall.php` already writes APP_URL to the real
        // domain when it creates `.env` from scratch — this only kicks
        // in for the other path onto this wizard, where `vendor/`/`.env`
        // already existed and skipped that script entirely, leaving
        // APP_URL sitting at the Laravel skeleton's generic default.
        $configuredUrl = config('app.url', 'http://localhost');
        $this->app_url = $configuredUrl === 'http://localhost'
            ? request()->getSchemeAndHttpHost()
            : $configuredUrl;

        $this->whatsapp_phone_id = (string) config('services.whatsapp.phone_id');
        $this->whatsapp_token = (string) config('services.whatsapp.token');
        $this->whatsapp_from = (string) config('services.whatsapp.from');
        $this->graph_tenant_id = (string) config('mail.mailers.microsoft-graph.tenant_id');
        $this->graph_client_id = (string) config('mail.mailers.microsoft-graph.client_id');
        $this->graph_client_secret = (string) config('mail.mailers.microsoft-graph.client_secret');

        // `public/preinstall.php` tags its handoff redirect with this
        // query string specifically when IT already collected and wrote
        // the database connection — the one case Step 2 would otherwise
        // ask the exact same question a second time. Scoped to that
        // marker rather than "does the currently configured connection
        // happen to work" so this never fires for an ordinary dev
        // environment that already has a working `.env`, or in tests
        // (where the default connection is always a trivially-connectable
        // SQLite `:memory:` database).
        if (request()->query('db') === 'configured') {
            $this->prefillDatabaseFromExistingConfig();
        }
    }

    /**
     * Pre-fills the database fields from whatever `.env` already has and
     * tests it once up front, so `continueFromRequirements()` can skip
     * straight past Step 2 when it's already configured and reachable.
     */
    private function prefillDatabaseFromExistingConfig(): void
    {
        $connection = config('database.default');

        if (! in_array($connection, ['mysql', 'sqlite'], true)) {
            return;
        }

        $this->db_connection = $connection;

        if ($connection === 'sqlite') {
            $this->db_database = (string) config('database.connections.sqlite.database');
        } else {
            $this->db_host = (string) config('database.connections.mysql.host');
            $this->db_port = (string) config('database.connections.mysql.port');
            $this->db_database = (string) config('database.connections.mysql.database');
            $this->db_username = (string) config('database.connections.mysql.username');
            $this->db_password = (string) config('database.connections.mysql.password');
        }

        if (blank($this->db_database)) {
            return;
        }

        $result = app(DatabaseConnectionTester::class)->test($this->databaseConfig());

        $this->connectionTested = $result['ok'];
        $this->connectionMessage = $result['message'];
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
     * Generated once and written to `.env` the moment it's needed — this
     * installation's own stable identity, sent as `fingerprint` on every
     * activate/verify call so the license server can tell it apart from
     * any other install sharing the same key.
     */
    private function installationId(): string
    {
        $existing = config('license.installation_id');

        if (filled($existing)) {
            return $existing;
        }

        $id = (string) Str::uuid();

        app(EnvironmentFileWriter::class)->set(['INSTALLATION_ID' => $id]);
        config(['license.installation_id' => $id]);

        return $id;
    }

    /**
     * Only ever talks to the license server and `.env` here — the
     * `licenses` table doesn't exist yet at this point in the wizard
     * (this step deliberately runs before Database/Install, since
     * activating needs internet, not a database). The actual `License`
     * row gets created later, once migrations have run, by the Install
     * step's own `license` task (see `installTasks()`/`recordLicense()`)
     * — using the plan/expiry captured here, still held on this same
     * Livewire component instance for the rest of the wizard's lifetime.
     */
    public function activateLicense(): void
    {
        $this->validate(['license_key' => ['required', 'string']]);

        $result = app(LicenseClient::class)->activate(
            $this->license_key,
            $this->installationId(),
            $this->app_url ?: config('app.url', 'http://localhost'),
        );

        $this->licenseActivated = $result['valid'];
        $this->licenseMessage = $result['valid']
            ? __('License activated.')
            : __('That key could not be activated: :reason', ['reason' => (string) $result['reason']]);

        if (! $result['valid']) {
            return;
        }

        $this->licensePlan = $result['plan'];
        $this->licenseExpiresAt = $result['expires_at'];

        app(EnvironmentFileWriter::class)->set(['LICENSE_SERVER_URL' => config('license.server_url')]);
    }

    public function continueFromLicense(): void
    {
        if ($this->licenseActivated !== true) {
            $this->addError('license_key', __('Activate the license before continuing.'));

            return;
        }

        if ($this->connectionTested === true) {
            // Database already configured (typically by preinstall.php)
            // and confirmed reachable at mount() — skip straight past the
            // Database step instead of asking for the same connection a
            // second time. Runtime config already reflects `.env` as-is
            // since Laravel booted with it; only a stale cached
            // config.php is worth guarding against here, same as
            // `saveDatabaseAndContinue()` does.
            Artisan::call('config:clear');
            $this->step = 4;

            return;
        }

        $this->step = 3;
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

        if ($property === 'license_key') {
            $this->licenseActivated = null;
            $this->licenseMessage = '';
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

        $this->step = 4;
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

        $this->step = 5;
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

        $this->step = 6;
    }

    /**
     * Both are optional — a fresh deployment that doesn't use WhatsApp
     * notifications or send mail through Microsoft Graph can leave every
     * field blank and continue; only the keys the operator actually
     * filled in are written, so a blank field never clobbers a value
     * already sitting in `.env` from some other source.
     */
    public function saveIntegrationsAndContinue(): void
    {
        $values = array_filter([
            'WHATSAPP_PHONE_ID' => $this->whatsapp_phone_id,
            'WHATSAPP_TOKEN' => $this->whatsapp_token,
            'WHATSAPP_FROM' => $this->whatsapp_from,
            'MICROSOFT_GRAPH_TENANT_ID' => $this->graph_tenant_id,
            'MICROSOFT_GRAPH_CLIENT_ID' => $this->graph_client_id,
            'MICROSOFT_GRAPH_CLIENT_SECRET' => $this->graph_client_secret,
        ], fn (string $value): bool => $value !== '');

        if ($values !== []) {
            app(EnvironmentFileWriter::class)->set($values);

            config([
                'services.whatsapp.phone_id' => $this->whatsapp_phone_id ?: config('services.whatsapp.phone_id'),
                'services.whatsapp.token' => $this->whatsapp_token ?: config('services.whatsapp.token'),
                'services.whatsapp.from' => $this->whatsapp_from ?: config('services.whatsapp.from'),
                'mail.mailers.microsoft-graph.tenant_id' => $this->graph_tenant_id ?: config('mail.mailers.microsoft-graph.tenant_id'),
                'mail.mailers.microsoft-graph.client_id' => $this->graph_client_id ?: config('mail.mailers.microsoft-graph.client_id'),
                'mail.mailers.microsoft-graph.client_secret' => $this->graph_client_secret ?: config('mail.mailers.microsoft-graph.client_secret'),
            ]);
        }

        $this->step = 7;
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
            'license' => __('Recording license'),
            'shield_generate' => __('Generating panel permissions'),
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
        $tasks['prune_modules'] = __('Removing unlicensed module files');

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
            'migrate' => $this->runMigrations(),
            'license' => $this->recordLicense(),
            'shield_generate' => $this->generateShieldPermissions(),
            'seed_core' => $this->seed([AllPermissionsSeeder::class, MatterPermissionsSeeder::class]),
            'seed_payroll' => $this->seed([PayrollModulePermissionsSeeder::class, IncentiveCalculationPermissionsSeeder::class]),
            'seed_calendar' => $this->seed([CalendarEventPermissionsSeeder::class]),
            'seed_pms' => $this->seed([PMSPermissionsSeeder::class, PMSConditionTemplatesSeeder::class, PMSPrintTemplatesSeeder::class]),
            'cache_clear' => $this->clearCaches(),
            'prune_modules' => $this->pruneUnlicensedModules(),
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
     * Persists the `License` row now that `migrate` has just created the
     * `licenses` table — the plan/expiry activateLicense() captured is
     * still sitting on this same component instance from Step 2, so this
     * never has to call the license server a second time.
     */
    private function recordLicense(): string
    {
        License::updateOrCreate(
            ['fingerprint' => $this->installationId()],
            [
                'key' => $this->license_key,
                'status' => 'active',
                'plan' => $this->licensePlan,
                'expires_at' => $this->licenseExpiresAt,
                'last_checked_at' => now(),
                'last_valid_at' => now(),
            ],
        );

        return __('License recorded.')."\n";
    }

    /**
     * A from-scratch migration run merges every registered migration
     * path — this app's own `database/migrations` plus every installed
     * package's own migrations directory — into one list sorted purely
     * by filename. At least one vendor package ships migrations named
     * `01_create_messages_table.php` etc. rather than proper timestamps,
     * which sorts BEFORE `2026_01_01_000000_create_users_table.php` and
     * tries to add a foreign key to `users` before that table exists.
     *
     * This can't be fixed by renaming this app's own `create_users_table`
     * migration — its filename is the primary key an already-migrated
     * production database uses to know it already ran; renaming it would
     * make Laravel think it's a brand-new, unrun migration everywhere
     * that database already exists, and try to create `users` a second
     * time. Running migrate twice instead — first restricted to this
     * app's own migrations directory (correctly ordered internally),
     * then unrestricted — achieves the same safe ordering with no
     * renaming at all: the first call creates `users` (and everything
     * else this app owns) before the second call ever reaches a
     * package's own migrations, and each migration only ever runs once
     * regardless, since `migrate` always skips whatever the `migrations`
     * table already has recorded.
     *
     * Uses `migrate:fresh` rather than plain `migrate` for the first call
     * — this is THE Install step of a first-run wizard, so a database
     * left half-migrated by an earlier failed attempt (a table created
     * before some later statement in the same batch failed, e.g. from
     * the exact ordering issue above) is a real, recurring case here, not
     * a hypothetical one: retrying with plain `migrate` would hit "table
     * already exists" for whatever got created last time, since nothing
     * recorded it as done. Dropping everything and starting clean every
     * time this step runs is the correct behavior for a step whose whole
     * job is "set up a brand-new database" — it would not be appropriate
     * for an ordinary `php artisan migrate` anywhere else in the app.
     */
    private function runMigrations(): string
    {
        return $this->runArtisan('migrate:fresh', ['--force' => true, '--path' => 'database/migrations'])
            .$this->runArtisan('migrate', ['--force' => true]);
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

    /**
     * Discovers every registered Shield resource/page/widget permission for
     * each panel the operator actually enabled in step 5 — silent, since
     * `--panel` and `--option` both being supplied skips Shield's own
     * interactive prompts. Only permission rows come out of this; no roles
     * are created or granted here (see AllPermissionsSeeder's own docblock
     * for why `super_admin` needs none).
     */
    private function generateShieldPermissions(): string
    {
        $output = '';

        foreach (array_keys(array_filter(['mms' => $this->module_mms, 'pms' => $this->module_pms])) as $panel) {
            $output .= $this->runArtisan('shield:generate', [
                '--panel' => $panel,
                '--option' => 'permissions',
                '--all' => true,
            ]);
        }

        return $output;
    }

    /**
     * One-way per deployment: physically relocates whichever module wasn't
     * selected in step 5 to cold storage (see {@see ModulePruner}), so its
     * source code isn't left sitting on a client's server for a module they
     * never licensed. The other, selected module is never touched.
     */
    private function pruneUnlicensedModules(): string
    {
        $pruner = app(ModulePruner::class);
        $output = '';

        if (! $this->module_mms) {
            $pruner->prune('mms');
            $output .= "Pruned MMS module files.\n";
        }

        if (! $this->module_pms) {
            $pruner->prune('pms');
            $output .= "Pruned PMS module files.\n";
        }

        return $output;
    }

    public function continueFromMigration(): void
    {
        if (! $this->migrated) {
            return;
        }

        $this->step = 8;
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

        $this->step = 9;
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
