<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `routes/web.php` only `require`s `routes/mms.php`/`routes/pms.php` behind
 * `config('modules.mms')`/`config('modules.pms')` — see the module-pruning
 * plan's routing prerequisite.
 *
 * The "both enabled" case is exercised at runtime (the default state this
 * dev box and every fresh install actually boots into). The "one module
 * disabled" cases are verified structurally instead of by toggling
 * `MODULE_*_ENABLED` and re-booting: `config/modules.php`'s flags are read
 * once, very early, while `bootstrap/app.php` itself is still being
 * evaluated (see `ModulePanelDefaultTest`, which needs `putenv()` before the
 * *original* application boot for the same reason it affects panel-provider
 * registration) — by the time a test method body runs, `routes/web.php` has
 * already executed with whatever the process's real `.env` had, and
 * `config()`/`refreshApplication()` calls made afterward do not reliably
 * re-run that early boot phase. Parsing the route files directly instead
 * confirms the actual thing this prerequisite is about: the `require`s are
 * gated by `config('modules.*')`, and each file only defines that module's
 * own routes.
 */
class ModuleRouteSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_modules_enabled_registers_every_route_by_default(): void
    {
        $this->assertTrue(Route::has('bulk-mail.unsubscribe'));
        $this->assertTrue(Route::has('incentive.calculation.print'));
        $this->assertTrue(Route::has('matter.received.accept'));
        $this->assertTrue(Route::has('leave-request.email-action.approve'));

        $this->assertTrue(Route::has('pms.quotations.print'));
        $this->assertTrue(Route::has('pms.leases.print'));
        $this->assertTrue(Route::has('pms.leases.tax-invoices'));
    }

    public function test_web_routes_conditionally_requires_the_mms_and_pms_route_files(): void
    {
        $contents = file_get_contents(base_path('routes/web.php'));

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*config\(\s*[\'"]modules\.mms[\'"]\s*\)\s*\)\s*\{\s*require\s+__DIR__\.[\'"]\/mms\.php[\'"]\s*;/',
            $contents
        );

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*config\(\s*[\'"]modules\.pms[\'"]\s*\)\s*\)\s*\{\s*require\s+__DIR__\.[\'"]\/pms\.php[\'"]\s*;/',
            $contents
        );
    }

    public function test_mms_route_file_only_defines_mms_routes(): void
    {
        $contents = file_get_contents(base_path('routes/mms.php'));

        $this->assertStringContainsString("name('bulk-mail.unsubscribe')", $contents);
        $this->assertStringContainsString("name('incentive.calculation.print')", $contents);
        $this->assertStringContainsString("name('matter.received.accept')", $contents);
        $this->assertStringContainsString("name('leave-request.email-action.approve')", $contents);

        $this->assertStringNotContainsString('pms.quotations.print', $contents);
        $this->assertStringNotContainsString('pms.leases.print', $contents);
    }

    public function test_pms_route_file_only_defines_pms_routes(): void
    {
        $contents = file_get_contents(base_path('routes/pms.php'));

        $this->assertStringContainsString("name('pms.quotations.print')", $contents);
        $this->assertStringContainsString("name('pms.leases.print')", $contents);
        $this->assertStringContainsString("name('pms.leases.tax-invoices')", $contents);
        $this->assertStringContainsString("name('pms.leases.receivable-receipt')", $contents);

        $this->assertStringNotContainsString('bulk-mail', $contents);
        $this->assertStringNotContainsString('incentive.calculation', $contents);
    }
}
