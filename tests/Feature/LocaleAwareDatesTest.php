<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Filament's ->date()/->dateTime() column helpers format through Carbon's
 * translatedFormat(), which reads CARBON's own locale — not the app's.
 * Nothing previously kept the two in sync: the language switcher's middleware
 * only ever called App::setLocale(), so every date across the panel rendered
 * with English month names regardless of which language the UI itself was
 * showing. App::setLocale() dispatches Illuminate\Foundation\Events\LocaleUpdated
 * on every call; AppServiceProvider now listens for it and mirrors the change
 * onto Carbon.
 */
class LocaleAwareDatesTest extends TestCase
{
    protected function tearDown(): void
    {
        App::setLocale(config('app.locale'));

        parent::tearDown();
    }

    public function test_switching_the_app_locale_switches_carbons_locale_too(): void
    {
        App::setLocale('ar');
        $this->assertSame('ar', Carbon::getLocale());

        App::setLocale('en');
        $this->assertSame('en', Carbon::getLocale());
    }

    public function test_a_formatted_date_actually_changes_language_with_the_locale(): void
    {
        $date = Carbon::parse('2026-09-15');

        App::setLocale('ar');
        $arabic = $date->translatedFormat('d M');

        App::setLocale('en');
        $english = $date->translatedFormat('d M');

        $this->assertNotSame($arabic, $english);
        $this->assertStringContainsString('Sep', $english);
        $this->assertStringNotContainsString('Sep', $arabic);
    }
}
