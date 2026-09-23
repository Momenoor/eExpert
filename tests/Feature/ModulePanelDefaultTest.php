<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * `bootstrap/providers.php` reads `MODULE_MMS_ENABLED`/`MODULE_PMS_ENABLED`
 * at BOOT time to decide which panel providers even get registered — so
 * these have to actually set the environment variable before the
 * application boots (hence `putenv()` ahead of `parent::setUp()`, each in
 * its own process) rather than just `config(['modules.mms' => ...])`,
 * which would be far too late to affect provider registration.
 *
 * Only `MmsPanelProvider` ever called `->default()`. Disabling MMS left
 * literally no panel marked default, which is exactly the "no default
 * panel" error an operator hit — Filament needs exactly one.
 */
class ModulePanelDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('MODULE_MMS_ENABLED');
        putenv('MODULE_PMS_ENABLED');

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    public function test_pms_becomes_the_default_panel_when_mms_is_disabled(): void
    {
        // Both set explicitly rather than leaving PMS to whatever the
        // real .env this process boots with happens to have — this test
        // broke once already from exactly that ambient state drifting
        // (PMS had been left disabled from a previous manual installer
        // run) despite the fix itself being correct.
        putenv('MODULE_MMS_ENABLED=false');
        putenv('MODULE_PMS_ENABLED=true');

        User::factory()->create();

        // The login page needs no authentication and renders through the
        // same panel-resolution path a "no default panel" error would
        // have crashed on — a clean 200 here is the actual thing under
        // test, not any particular authenticated page.
        $this->get('/pms/login')->assertSuccessful();
    }

    #[RunInSeparateProcess]
    public function test_mms_stays_the_default_panel_when_pms_is_disabled(): void
    {
        putenv('MODULE_MMS_ENABLED=true');
        putenv('MODULE_PMS_ENABLED=false');

        User::factory()->create();

        $this->get('/mms/login')->assertSuccessful();
    }
}
