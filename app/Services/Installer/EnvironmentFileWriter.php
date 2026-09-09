<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\File;

/**
 * Writes key/value pairs into the application's `.env` file.
 *
 * Only ever called from the installer, and only ever with values the operator
 * just typed into the wizard — nothing here reads from user-submitted HTTP
 * input directly, so there is no path from an arbitrary request to arbitrary
 * lines landing in `.env`.
 */
class EnvironmentFileWriter
{
    public function __construct(
        private readonly string $path = '',
    ) {}

    private function envPath(): string
    {
        return $this->path !== '' ? $this->path : base_path('.env');
    }

    /**
     * Ensure `.env` exists, seeding it from `.env.example` on a fresh checkout.
     */
    public function ensureExists(): void
    {
        if (File::exists($this->envPath())) {
            return;
        }

        $example = base_path('.env.example');

        File::put($this->envPath(), File::exists($example) ? File::get($example) : '');
    }

    /**
     * Set one or more KEY=value pairs, replacing an existing line for that key
     * or appending a new one.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $this->ensureExists();

        $contents = File::get($this->envPath());

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote((string) $value);

            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        File::put($this->envPath(), $contents);
    }

    /**
     * Quote a value only when it needs it — an unquoted value with a space in
     * it is silently truncated by Laravel's own .env parser at the first space.
     */
    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\'\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
