<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Before anything Composer-dependent runs: on a from-scratch deployment
// — including one where `vendor/` and even a stub `.env` were copied
// along with the rest of the project folder — requiring the autoloader
// below with no real `APP_KEY`/database configured yet either fatal
// errors or dumps the operator straight into Laravel's own wizard
// without preinstall.php's composer/npm/database-creation checks ever
// running. `preinstall.php` has zero Composer dependencies for exactly
// this reason; send the request there instead, unless it's already the
// request for that file, until `.env` has progressed past a fresh copy
// of `.env.example` (an app key and a database name both set — exactly
// what preinstall.php's own Database step writes once it succeeds).

$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/\\');

$preinstallUri = $baseUrl.'/preinstall.php';
$currentUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

if (! env_looks_configured(__DIR__.'/../.env') && $currentUri !== $preinstallUri) {
    header("Location: {$preinstallUri}");
    exit;
}

function env_looks_configured(string $envPath): bool
{
    if (! file_exists(__DIR__.'/../vendor/autoload.php') || ! file_exists($envPath)) {
        return false;
    }

    $contents = file_get_contents($envPath);

    return preg_match('/^APP_KEY=.+/m', $contents) === 1
        && preg_match('/^DB_DATABASE=.+/m', $contents) === 1;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
