<?php

namespace App\Providers;

use App\Events\FilamentActionEvent;
use App\Listeners\SendFilamentActionNotifications;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            FilamentActionEvent::class,
            SendFilamentActionNotifications::class,
        );
    }
}
