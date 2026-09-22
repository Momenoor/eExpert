<?php

namespace App\Services\Installer;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs `composer install` / `npm run build` on the operator's behalf when
 * the requirements step finds the binaries actually available — and does
 * nothing beyond reporting when it doesn't, since a missing binary or a
 * disabled `proc_open` means the operator has to run these themselves.
 *
 * Mirrors `public/preinstall.php`'s framework-free version of the same
 * idea — that script can't `use` this class since it has to run before
 * Composer's autoloader exists, so the same shell-out logic is
 * necessarily duplicated there in plain PHP.
 */
class PackageInstaller
{
    public function composerAvailable(): bool
    {
        return $this->binaryAvailable('composer --version');
    }

    public function npmAvailable(): bool
    {
        return $this->binaryAvailable('npm --version');
    }

    public function vendorInstalled(): bool
    {
        return file_exists(base_path('vendor/autoload.php'));
    }

    public function frontendBuilt(): bool
    {
        return file_exists(public_path('build/manifest.json'))
            || file_exists(public_path('build/.vite/manifest.json'));
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function runComposerInstall(): array
    {
        return $this->run(['composer', 'install', '--no-dev', '--optimize-autoloader'], timeout: 600);
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function runNpmBuild(): array
    {
        $install = $this->run(['npm', 'install'], timeout: 600);

        if (! $install['ok']) {
            return $install;
        }

        $build = $this->run(['npm', 'run', 'build'], timeout: 600);

        return [
            'ok' => $build['ok'],
            'output' => $install['output']."\n".$build['output'],
        ];
    }

    private function binaryAvailable(string $versionCommand): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        return $this->run(explode(' ', $versionCommand), timeout: 10)['ok'];
    }

    /**
     * @param  list<string>  $command
     * @return array{ok: bool, output: string}
     */
    private function run(array $command, int $timeout): array
    {
        if (! function_exists('proc_open')) {
            return ['ok' => false, 'output' => __('The proc_open function is disabled on this server.')];
        }

        try {
            $process = new Process($command, base_path(), null, null, $timeout);
            $process->run();

            return [
                'ok' => $process->isSuccessful(),
                'output' => $process->getOutput().$process->getErrorOutput(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }
}
