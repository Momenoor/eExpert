<?php

namespace App\Console\Commands;

use App\Services\Installer\EnvironmentFileWriter;
use App\Services\Installer\ModulePruner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Restores a module the installer previously pruned (see {@see ModulePruner})
 * because the client's original license didn't cover it, then flips its
 * `.env` flag back on.
 *
 * A deliberate manual step the vendor runs (e.g. over SSH) when a client
 * upgrades their license to add the second module — it only touches the
 * target module's stashed files and its own `.env` flag, never anything
 * belonging to the already-running module.
 */
class EnableModule extends Command
{
    protected $signature = 'module:enable {module : mms or pms} {--force : Restore even if the stash looks empty}';

    protected $description = "Restore a previously pruned module's files and enable it.";

    public function handle(ModulePruner $pruner, EnvironmentFileWriter $env): int
    {
        $module = strtolower((string) $this->argument('module'));

        if (! in_array($module, ['mms', 'pms'], true)) {
            $this->error("Invalid module \"{$module}\" — expected \"mms\" or \"pms\".");

            return self::FAILURE;
        }

        if (! $pruner->isPruned($module) && ! $this->option('force')) {
            $this->error(
                "Nothing to restore for \"{$module}\" — it doesn't look pruned on this deployment. ".
                'If its files are simply missing (e.g. the disk was cleaned), a fresh redeploy of '.
                "that module's files is needed instead of this command."
            );

            return self::FAILURE;
        }

        $pruner->restore($module);

        $env->set(['MODULE_'.strtoupper($module).'_ENABLED' => 'true']);

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->info(ucfirst($module).' restored and enabled.');

        return self::SUCCESS;
    }
}
