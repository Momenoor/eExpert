<?php

namespace App\Providers;

use App\Events\FilamentActionEvent;
use App\Listeners\SendFilamentActionNotifications;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Events\LocaleUpdated;
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

        // The language switcher's middleware calls App::setLocale() on every
        // request, which only changes __()/trans() — it never touches Carbon's
        // OWN locale. Filament's ->date()/->dateTime() column helpers format
        // through Carbon::translatedFormat(), which reads Carbon's locale, not
        // the app's, so every date across the panel kept rendering with
        // English month names ("Sep") under an otherwise fully Arabic UI.
        // App::setLocale() dispatches this event on every call, so listening
        // here keeps the two in sync without depending on which middleware or
        // code path changed the locale.
        Event::listen(
            LocaleUpdated::class,
            fn (LocaleUpdated $event) => Carbon::setLocale($event->locale),
        );

        Setting::applyMailConfig();
    }
}
