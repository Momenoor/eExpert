<?php

namespace Tests\Unit\Services\Installer;

use App\Services\Installer\ModulePruner;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Exercises {@see ModulePruner} entirely against a fake manifest pointing at
 * throwaway fixture files/directories under `storage/framework/testing/` —
 * never against real application paths like `app/Filament/Mms`. Pruning is a
 * one-way, destructive move on a real deployment; this dev repo runs both
 * modules, so nothing here may touch actual module source.
 */
class ModulePrunerTest extends TestCase
{
    private const string MODULE = 'test_module';

    private string $fixtureRoot;

    private string $fixtureRelative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = storage_path('framework/testing/module-pruner-'.uniqid());
        $this->fixtureRelative = $this->relativeToBasePath($this->fixtureRoot);

        File::ensureDirectoryExists($this->fixtureRoot.'/some-dir');
        File::put($this->fixtureRoot.'/some-dir/nested.php', '<?php // nested fixture file');
        File::put($this->fixtureRoot.'/standalone.php', '<?php // standalone fixture file');

        config([
            'module_manifest.'.self::MODULE.'.paths' => [
                $this->fixtureRelative.'/some-dir',
                $this->fixtureRelative.'/standalone.php',
                $this->fixtureRelative.'/does-not-exist.php',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);
        File::deleteDirectory(storage_path('app/disabled-modules/'.self::MODULE));

        parent::tearDown();
    }

    private function relativeToBasePath(string $absolute): string
    {
        return ltrim(str_replace(base_path(), '', $absolute), DIRECTORY_SEPARATOR.'/\\');
    }

    private function stashPath(string $suffix = ''): string
    {
        return storage_path('app/disabled-modules/'.self::MODULE).'/'.$this->fixtureRelative.$suffix;
    }

    public function test_it_is_not_pruned_before_anything_runs(): void
    {
        $this->assertFalse(app(ModulePruner::class)->isPruned(self::MODULE));
    }

    public function test_prune_moves_manifest_paths_into_cold_storage(): void
    {
        app(ModulePruner::class)->prune(self::MODULE);

        $this->assertFalse(File::exists($this->fixtureRoot.'/some-dir'));
        $this->assertFalse(File::exists($this->fixtureRoot.'/standalone.php'));

        $this->assertTrue(File::exists($this->stashPath('/some-dir/nested.php')));
        $this->assertTrue(File::exists($this->stashPath('/standalone.php')));

        $this->assertTrue(app(ModulePruner::class)->isPruned(self::MODULE));
    }

    public function test_prune_skips_manifest_paths_that_do_not_exist(): void
    {
        // Should not throw despite the manifest listing a nonexistent path.
        app(ModulePruner::class)->prune(self::MODULE);

        $this->assertFalse(File::exists($this->stashPath('/does-not-exist.php')));
    }

    public function test_prune_is_a_no_op_when_nothing_in_the_manifest_exists(): void
    {
        File::deleteDirectory($this->fixtureRoot.'/some-dir');
        File::delete($this->fixtureRoot.'/standalone.php');

        app(ModulePruner::class)->prune(self::MODULE);

        $this->assertFalse(app(ModulePruner::class)->isPruned(self::MODULE));
    }

    public function test_restore_moves_everything_back(): void
    {
        $pruner = app(ModulePruner::class);

        $pruner->prune(self::MODULE);
        $pruner->restore(self::MODULE);

        $this->assertTrue(File::exists($this->fixtureRoot.'/some-dir/nested.php'));
        $this->assertTrue(File::exists($this->fixtureRoot.'/standalone.php'));

        $this->assertFalse($pruner->isPruned(self::MODULE));
    }

    public function test_restore_is_a_no_op_when_nothing_was_pruned(): void
    {
        $pruner = app(ModulePruner::class);

        $pruner->restore(self::MODULE);

        $this->assertFalse(File::exists($this->stashPath()));
        $this->assertTrue(File::exists($this->fixtureRoot.'/standalone.php'));
    }
}
