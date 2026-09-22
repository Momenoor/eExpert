<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Before anything Composer-dependent runs: on a from-scratch deployment
// with no `vendor/` or `.env` yet, requiring the autoloader below is a
// fatal error and nothing else — not even a Laravel middleware — ever
// gets a chance to help. `preinstall.php` has zero Composer dependencies
// for exactly that reason; send the request there instead, unless it's
// already the request for that file.
if ((! file_exists(__DIR__.'/../vendor/autoload.php') || ! file_exists(__DIR__.'/../.env'))
    && ($_SERVER['SCRIPT_NAME'] ?? '') !== '/preinstall.php') {
    header('Location: /preinstall.php');
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
