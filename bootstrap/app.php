<?php

use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\RedirectToInstaller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs before everything else in the web group: a request has to be
        // routed to the installer before session/auth/CSRF middleware get a
        // chance to assume a working, migrated database exists.
        $middleware->web(prepend: [RedirectToInstaller::class]);

        $middleware->alias([
            'installer.guard' => RedirectIfInstalled::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('matter:confirm-receiving')->everyMinute()->withoutOverlapping();
        $schedule->command('mail:send-bulk-campaigns')->everyMinute()->withoutOverlapping();
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
