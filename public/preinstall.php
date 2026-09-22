<?php

declare(strict_types=1);

/**
 * Pre-flight installer — runs BEFORE Laravel itself can boot.
 *
 * `public/index.php` unconditionally requires `vendor/autoload.php`; on a
 * from-scratch deployment (a fresh clone/upload with no `composer install`
 * or `.env` yet) that line is a fatal error, so nothing framework-based —
 * not even a middleware redirect — can run to help the operator. This file
 * has zero Composer-package dependencies for exactly that reason: it is
 * the one thing guaranteed to still work in that state.
 *
 * `index.php` redirects here itself (see the guard at its top) whenever
 * `vendor/autoload.php` or `.env` is missing. Once both exist and the
 * database is reachable, this script hands off to `/install` — the real,
 * framework-based wizard in `App\Livewire\Installer\InstallWizard` — for
 * everything else (application details, module selection, admin account).
 *
 * Deliberately duplicates a little logic that also exists as proper
 * Laravel code once the framework is available (`ServerRequirementsChecker`,
 * `PackageInstaller`, `EnvironmentFileWriter`) — this file can't `use` any
 * of those classes, since the autoloader that would resolve them is
 * exactly what might not exist yet.
 */
const BASE_PATH = __DIR__.'/..';

const REQUIRED_EXTENSIONS = [
    'pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'zip',
];

const MINIMUM_PHP_VERSION = '8.5.0';

function vendor_installed(): bool
{
    return file_exists(BASE_PATH.'/vendor/autoload.php');
}

function env_exists(): bool
{
    return file_exists(BASE_PATH.'/.env');
}

function frontend_built(): bool
{
    return file_exists(BASE_PATH.'/public/build/manifest.json')
        || file_exists(BASE_PATH.'/public/build/.vite/manifest.json');
}

function binary_available(string $command): bool
{
    if (! function_exists('proc_open')) {
        return false;
    }

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($command, $descriptors, $pipes, BASE_PATH);

    if (! is_resource($process)) {
        return false;
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return $exitCode === 0;
}

/**
 * @return array{ok: bool, output: string}
 */
function run_command(string $command): array
{
    if (! function_exists('proc_open')) {
        return ['ok' => false, 'output' => 'The proc_open function is disabled on this server.'];
    }

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, BASE_PATH);

    if (! is_resource($process)) {
        return ['ok' => false, 'output' => 'Could not start the command.'];
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['ok' => $exitCode === 0, 'output' => $output];
}

function generate_app_key(): string
{
    return 'base64:'.base64_encode(random_bytes(32));
}

/**
 * Replaces an existing KEY=value line in `.env` or appends a new one —
 * the same "replace or append" contract as the framework-side
 * `EnvironmentFileWriter::set()`, reimplemented here without it.
 *
 * @param  array<string, string>  $values
 */
function write_env(array $values): void
{
    $path = BASE_PATH.'/.env';
    $contents = file_exists($path) ? file_get_contents($path) : '';

    foreach ($values as $key => $value) {
        $needsQuotes = $value === '' || preg_match('/[\s#"\'\\\\]/', $value) === 1;
        $quoted = $needsQuotes ? '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"' : $value;
        $line = $key.'='.$quoted;

        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        $contents = preg_match($pattern, $contents) === 1
            ? preg_replace($pattern, $line, $contents)
            : rtrim($contents)."\n".$line."\n";
    }

    file_put_contents($path, $contents);
}

function ensure_env_exists(): void
{
    if (env_exists()) {
        return;
    }

    $example = BASE_PATH.'/.env.example';
    file_put_contents(BASE_PATH.'/.env', file_exists($example) ? file_get_contents($example) : '');
}

/**
 * @return array{ok: bool, message: string}
 */
function test_database(string $connection, string $host, string $port, string $database, string $username, string $password): array
{
    try {
        if ($connection === 'sqlite') {
            $path = str_starts_with($database, '/') || preg_match('/^[A-Za-z]:/', $database) === 1
                ? $database
                : BASE_PATH.'/'.$database;

            if (! file_exists($path)) {
                touch($path);
            }

            new PDO('sqlite:'.$path);

            return ['ok' => true, 'message' => 'Connection successful.'];
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);

            return ['ok' => true, 'message' => 'Connection successful.'];
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Unknown database') === false) {
                return ['ok' => false, 'message' => $e->getMessage()];
            }

            // The server is reachable, only the named database doesn't
            // exist yet — create it, exactly the case a brand-new server
            // hits every time.
            $rootDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($rootDsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);
            $identifier = str_replace('`', '``', $database);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$identifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 5]);

            return ['ok' => true, 'message' => 'Database created and connection successful.'];
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

// ---------------------------------------------------------------------
// Request handling — plain POST actions, no router.
// ---------------------------------------------------------------------

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'install_composer' && binary_available('composer --version')) {
        $result = run_command('composer install --no-dev --optimize-autoloader');
        $success = $result['ok'] ? 'Composer dependencies installed.' : null;
        $error = $result['ok'] ? null : $result['output'];
    } elseif ($action === 'build_frontend' && binary_available('npm --version')) {
        $result = run_command('npm install && npm run build');
        $success = $result['ok'] ? 'Frontend built.' : null;
        $error = $result['ok'] ? null : $result['output'];
    } elseif ($action === 'save_database') {
        ensure_env_exists();

        if (! preg_match('/^APP_KEY=.+/m', file_get_contents(BASE_PATH.'/.env') ?: '')) {
            write_env(['APP_KEY' => generate_app_key()]);
        }

        $connection = $_POST['db_connection'] === 'sqlite' ? 'sqlite' : 'mysql';
        $database = trim((string) ($_POST['db_database'] ?? ''));
        $host = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $port = trim((string) ($_POST['db_port'] ?? '3306'));
        $username = (string) ($_POST['db_username'] ?? '');
        $password = (string) ($_POST['db_password'] ?? '');

        if ($database === '') {
            $error = 'A database name is required.';
        } else {
            $result = test_database($connection, $host, $port, $database, $username, $password);

            if ($result['ok']) {
                write_env($connection === 'sqlite'
                    ? ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database]
                    : [
                        'DB_CONNECTION' => 'mysql',
                        'DB_HOST' => $host,
                        'DB_PORT' => $port,
                        'DB_DATABASE' => $database,
                        'DB_USERNAME' => $username,
                        'DB_PASSWORD' => $password,
                    ]);

                header('Location: /install?db=configured');
                exit;
            }

            $error = $result['message'];
        }
    }
}

$phpOk = version_compare(PHP_VERSION, MINIMUM_PHP_VERSION, '>=');
$missingExtensions = array_values(array_filter(REQUIRED_EXTENSIONS, fn (string $ext): bool => ! extension_loaded($ext)));
$vendorOk = vendor_installed();
$frontendOk = frontend_built();
$envOk = env_exists();
$composerAvailable = binary_available('composer --version');
$npmAvailable = binary_available('npm --version');

$readyForDatabaseStep = $phpOk && $missingExtensions === [] && $vendorOk;

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application Setup — Pre-flight</title>
    <style>
        :root { color-scheme: light; --bg:#f5f6f8; --surface:#fff; --border:#e2e5ea; --text:#1f2430; --muted:#667085; --primary:#2f5233; --primary-hover:#24401f; --danger:#b3261e; --success:#1b7a43; --warning:#a15c00; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; }
        .shell { max-width:720px; margin:0 auto; padding:48px 20px 80px; }
        h1 { font-size:22px; text-align:center; margin:0 0 4px; }
        p.lead { text-align:center; color:var(--muted); margin:0 0 32px; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:28px; margin-bottom:20px; }
        .card h2 { margin-top:0; font-size:17px; }
        .check-list { list-style:none; margin:0 0 16px; padding:0; }
        .check-list li { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:7px 0; border-bottom:1px solid var(--border); font-size:13px; }
        .check-list li:last-child { border-bottom:none; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge.ok { background:#e4f5ea; color:var(--success); }
        .badge.fail { background:#fbe7e6; color:var(--danger); }
        .badge.warn { background:#fdf1de; color:var(--warning); }
        .field { margin-bottom:16px; }
        .field label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; }
        .field input, .field select { width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:14px; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:9px 18px; border-radius:6px; border:1px solid transparent; font-size:14px; font-weight:600; cursor:pointer; background:var(--primary); color:#fff; }
        .btn:hover { background:var(--primary-hover); }
        .btn.secondary { background:transparent; color:var(--text); border-color:var(--border); }
        .btn[disabled] { opacity:.5; cursor:not-allowed; }
        .actions { display:flex; justify-content:flex-end; gap:10px; margin-top:20px; }
        .alert { padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; white-space:pre-wrap; }
        .alert.success { background:#e4f5ea; color:var(--success); }
        .alert.danger { background:#fbe7e6; color:var(--danger); }
        code { background:#eef0f3; padding:1px 5px; border-radius:4px; }
    </style>
</head>
<body>
    <div class="shell">
        <h1>Application Setup</h1>
        <p class="lead">Pre-flight checks — this page runs before the application itself can start.</p>

        <?php if ($error) { ?>
            <div class="alert danger"><?= htmlspecialchars($error) ?></div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php } ?>

        <div class="card">
            <h2>Server Requirements</h2>
            <ul class="check-list">
                <li>
                    <span>PHP version — installed: <?= htmlspecialchars(PHP_VERSION) ?>, requires <?= MINIMUM_PHP_VERSION ?>+</span>
                    <span class="badge <?= $phpOk ? 'ok' : 'fail' ?>"><?= $phpOk ? 'OK' : 'Failing' ?></span>
                </li>
                <?php foreach (REQUIRED_EXTENSIONS as $ext) { ?>
                    <li>
                        <span>PHP extension: <?= htmlspecialchars($ext) ?></span>
                        <span class="badge <?= extension_loaded($ext) ? 'ok' : 'fail' ?>"><?= extension_loaded($ext) ? 'Loaded' : 'Missing' ?></span>
                    </li>
                <?php } ?>
            </ul>
        </div>

        <div class="card">
            <h2>Composer Dependencies</h2>
            <ul class="check-list">
                <li>
                    <span>vendor/ (composer install)</span>
                    <span class="badge <?= $vendorOk ? 'ok' : 'warn' ?>"><?= $vendorOk ? 'Installed' : 'Missing' ?></span>
                </li>
            </ul>
            <?php if (! $vendorOk) { ?>
                <?php if ($composerAvailable) { ?>
                    <form method="post">
                        <input type="hidden" name="action" value="install_composer">
                        <div class="actions">
                            <button type="submit" class="btn">Run composer install</button>
                        </div>
                    </form>
                <?php } else { ?>
                    <p>Composer wasn't detected on this server. Run this yourself, then refresh this page:</p>
                    <p><code>composer install --no-dev --optimize-autoloader</code></p>
                <?php } ?>
            <?php } ?>
        </div>

        <div class="card">
            <h2>Frontend Build</h2>
            <ul class="check-list">
                <li>
                    <span>public/build (npm run build)</span>
                    <span class="badge <?= $frontendOk ? 'ok' : 'warn' ?>"><?= $frontendOk ? 'Built' : 'Missing' ?></span>
                </li>
            </ul>
            <?php if (! $frontendOk) { ?>
                <?php if ($npmAvailable) { ?>
                    <form method="post">
                        <input type="hidden" name="action" value="build_frontend">
                        <div class="actions">
                            <button type="submit" class="btn">Run npm install &amp;&amp; npm run build</button>
                        </div>
                    </form>
                <?php } else { ?>
                    <p>Node/npm weren't detected on this server. Run this yourself, then refresh this page:</p>
                    <p><code>npm install && npm run build</code></p>
                <?php } ?>
            <?php } ?>
            <p style="color:var(--muted); font-size:12px;">This can be finished later from inside the wizard's own Requirements step — it isn't required to continue past this page.</p>
        </div>

        <?php if ($readyForDatabaseStep) { ?>
            <div class="card">
                <h2>Database Connection</h2>
                <p style="color:var(--muted); font-size:12px;">If the database doesn't exist yet, it will be created automatically.</p>
                <form method="post">
                    <input type="hidden" name="action" value="save_database">
                    <div class="field">
                        <label>Connection</label>
                        <select name="db_connection" onchange="document.getElementById('mysql-fields').style.display = this.value === 'mysql' ? 'block' : 'none'">
                            <option value="mysql">MySQL</option>
                            <option value="sqlite">SQLite</option>
                        </select>
                    </div>
                    <div id="mysql-fields">
                        <div class="row">
                            <div class="field">
                                <label>Host</label>
                                <input type="text" name="db_host" value="127.0.0.1">
                            </div>
                            <div class="field">
                                <label>Port</label>
                                <input type="text" name="db_port" value="3306">
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label>Username</label>
                                <input type="text" name="db_username">
                            </div>
                            <div class="field">
                                <label>Password</label>
                                <input type="password" name="db_password">
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Database Name (or file path for SQLite)</label>
                        <input type="text" name="db_database" required>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn">Create Database &amp; Continue</button>
                    </div>
                </form>
            </div>
        <?php } elseif ($vendorOk) { ?>
            <div class="card">
                <p>Fix the failing requirements above, then refresh this page.</p>
            </div>
        <?php } ?>
    </div>
</body>
</html>
