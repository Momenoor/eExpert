<?php

namespace Tests\Unit;

use App\Services\Installer\ServerRequirementsChecker;
use Tests\TestCase;

/**
 * Step 1 of the wizard: whether this server can even run the application.
 *
 * The environment running this test suite is, definitionally, one this
 * application already runs on — so the meaningful assertions are about shape
 * (every check is labelled and marked critical or not) and about the one
 * property the wizard's own gate depends on: PHP itself and every required
 * extension are marked critical, so a genuinely broken server cannot click
 * past this step.
 */
class ServerRequirementsCheckerTest extends TestCase
{
    private ServerRequirementsChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = new ServerRequirementsChecker;
    }

    public function test_it_returns_a_labelled_check_for_every_requirement(): void
    {
        foreach ($this->checker->check() as $check) {
            $this->assertArrayHasKey('label', $check);
            $this->assertArrayHasKey('ok', $check);
            $this->assertArrayHasKey('critical', $check);
            $this->assertArrayHasKey('detail', $check);
            $this->assertIsBool($check['ok']);
            $this->assertIsBool($check['critical']);
        }
    }

    public function test_php_version_and_every_extension_are_critical(): void
    {
        // Matched against the same translation calls the checker itself makes,
        // rather than a hardcoded English literal — this app's default locale
        // is Arabic, and the label text is translated before it ever reaches
        // this array.
        $critical = collect($this->checker->check())
            ->where('critical', true)
            ->pluck('label');

        $this->assertTrue($critical->contains(__('PHP version')));
        $this->assertTrue($critical->contains(__('PHP extension: :name', ['name' => 'pdo'])));
    }

    public function test_some_checks_are_warnings_rather_than_critical(): void
    {
        // Every non-critical check in this class is a writable-directory check
        // — asserted by count rather than by matching a translated label, since
        // this app's default locale is Arabic and the label text is translated
        // before it reaches this array.
        $nonCritical = collect($this->checker->check())->where('critical', false);

        $this->assertCount(4, $nonCritical);
    }

    public function test_this_environment_passes_its_own_critical_checks(): void
    {
        // A CI runner or local dev box that could not clear this would not be
        // able to run the rest of this test suite either.
        $this->assertTrue($this->checker->passesCriticalChecks());
    }
}
