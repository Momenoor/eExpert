<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Every string the app asks for must have an Arabic translation.
 *
 * Arabic is the working locale here, and a key with no entry does not fail
 * loudly — Laravel returns the key itself, so an untranslated label reaches the
 * screen looking almost right ("Export All") or, for a dotted key, as visible
 * junk ("notifications.new"). Nothing catches that but a reader, which is how
 * seventy-odd strings accumulated.
 */
class TranslationCoverageTest extends TestCase
{
    /**
     * Published assets of packages that are no longer installed. Nothing renders
     * these views, and their `::` namespace is not registered, so their keys can
     * never resolve.
     *
     * @var list<string>
     */
    private const ORPHANED_VENDOR_PREFIXES = [
        'filament-ui-switcher::',
    ];

    public function test_every_translation_key_used_by_the_app_exists_in_arabic(): void
    {
        $keys = $this->translationKeys();

        // A regex that quietly stops matching would make this test pass while
        // checking nothing, so assert the harvest is still plausible first.
        $this->assertGreaterThan(500, count($keys), 'the key scan found suspiciously few strings');

        $missing = [];

        foreach ($keys as $key => $file) {
            foreach (self::ORPHANED_VENDOR_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    continue 2;
                }
            }

            if (! Lang::has($key, 'ar')) {
                $missing[] = "{$key}   ({$file})";
            }
        }

        $this->assertSame([], $missing, "Untranslated in ar:\n".implode("\n", $missing));
    }

    /**
     * Keys passed to __() anywhere in first-party code.
     *
     * @return array<string, string> key => the file it was found in
     */
    private function translationKeys(): array
    {
        $roots = [base_path('app'), base_path('resources/views'), base_path('routes')];
        $keys = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname()) ?: '';
                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

                preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $contents, $single);
                preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $contents, $double);

                foreach ([$single[1], $double[1]] as $matches) {
                    foreach ($matches as $match) {
                        $keys[stripslashes($match)] ??= $relative;
                    }
                }
            }
        }

        return $keys;
    }
}
