<?php

namespace App\Services\Installer;

/**
 * Step 1 of the wizard: can this server actually run the application.
 *
 * Split into two severities on purpose. A missing PHP extension means the app
 * cannot function at all — every check in that list must pass before the
 * wizard lets the operator continue. A non-writable storage directory is
 * usually just a permissions command away and is shown as a warning so the
 * operator can fix it and retry without restarting the whole flow.
 */
class ServerRequirementsChecker
{
    /**
     * @var list<string>
     */
    private const REQUIRED_EXTENSIONS = [
        'pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'zip',
    ];

    private const MINIMUM_PHP_VERSION = '8.2.0';

    /**
     * @var list<string>
     */
    private const WRITABLE_PATHS = [
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    /**
     * @return array<int, array{label: string, ok: bool, critical: bool, detail: string}>
     */
    public function check(): array
    {
        $checks = [];

        $checks[] = [
            'label' => __('PHP version'),
            'ok' => version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>='),
            'critical' => true,
            'detail' => __('Installed: :version — requires :minimum or newer.', [
                'version' => PHP_VERSION,
                'minimum' => self::MINIMUM_PHP_VERSION,
            ]),
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => __('PHP extension: :name', ['name' => $extension]),
                'ok' => extension_loaded($extension),
                'critical' => true,
                'detail' => extension_loaded($extension) ? __('Loaded') : __('Missing'),
            ];
        }

        foreach (self::WRITABLE_PATHS as $relative) {
            $path = base_path($relative);

            $checks[] = [
                'label' => __('Writable: :path', ['path' => $relative]),
                'ok' => is_dir($path) && is_writable($path),
                'critical' => false,
                'detail' => is_dir($path)
                    ? (is_writable($path) ? __('Writable') : __('Not writable'))
                    : __('Directory does not exist'),
            ];
        }

        return $checks;
    }

    public function passesCriticalChecks(): bool
    {
        return collect($this->check())
            ->where('critical', true)
            ->every(fn (array $check): bool => $check['ok']);
    }
}
