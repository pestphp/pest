<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;
use Pest\Browser\Support\BrowserTestIdentifier;
use Pest\Factories\TestCaseFactory;
use Pest\TestSuite;

/**
 * Watch patterns for frontend assets that affect browser tests.
 *
 * Uses `BrowserTestIdentifier` from pest-plugin-browser (if installed) to
 * auto-discover directories containing browser tests. Falls back to the
 * `tests/Browser` convention when the plugin is absent.
 *
 * @internal
 */
final readonly class Browser implements WatchDefault
{
    public function applicable(): bool
    {
        // Browser tests can exist in any PHP project. We only activate when
        // there is an actual `tests/Browser` directory OR pest-plugin-browser
        // is installed.
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('pestphp/pest-plugin-browser');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        $browserDirs = $this->detectBrowserTestDirs($projectRoot, $testPath);

        $globs = [
            'resources/js/**/*.js',
            'resources/js/**/*.ts',
            'resources/js/**/*.tsx',
            'resources/js/**/*.jsx',
            'resources/js/**/*.vue',
            'resources/js/**/*.svelte',
            'resources/css/**/*.css',
            'resources/css/**/*.scss',
            'resources/css/**/*.less',
            // Vite / Webpack build output that browser tests may consume.
            'public/build/**/*.js',
            'public/build/**/*.css',
        ];

        $patterns = [];

        foreach ($globs as $glob) {
            $patterns[$glob] = $browserDirs;
        }

        return $patterns;
    }

    /**
     * @return array<int, string>
     */
    private function detectBrowserTestDirs(string $projectRoot, string $testPath): array
    {
        $dirs = [];

        $candidate = $testPath.'/Browser';

        if (is_dir($projectRoot.DIRECTORY_SEPARATOR.$candidate)) {
            $dirs[] = $candidate;
        }

        // Scan TestRepository via BrowserTestIdentifier if pest-plugin-browser
        // is installed to find tests using `visit()` outside the conventional
        // Browser/ folder.
        if (class_exists(BrowserTestIdentifier::class)) {
            $repo = TestSuite::getInstance()->tests;

            foreach ($repo->getFilenames() as $filename) {
                $factory = $repo->get($filename);

                if (! $factory instanceof TestCaseFactory) {
                    continue;
                }

                foreach ($factory->methods as $method) {
                    if (BrowserTestIdentifier::isBrowserTest($method)) {
                        $rel = $this->fileRelative($projectRoot, $filename);

                        if ($rel !== null) {
                            $dirs[] = dirname($rel);
                        }

                        break;
                    }
                }
            }
        }

        return array_values(array_unique($dirs === [] ? [$testPath] : $dirs));
    }

    private function fileRelative(string $projectRoot, string $path): ?string
    {
        $real = @realpath($path);

        if ($real === false) {
            $real = $path;
        }

        $root = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($real, $root)) {
            return null;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root)));
    }
}
