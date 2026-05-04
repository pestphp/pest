<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Composer\InstalledVersions;
use Pest\Browser\Support\BrowserTestIdentifier;
use Pest\Factories\TestCaseFactory;
use Pest\Plugins\Tia\Contracts\WatchDefault;
use Pest\TestSuite;

/**
 * @internal
 */
final readonly class Browser implements WatchDefault
{
    public function applicable(): bool
    {
        return class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('pestphp/pest-plugin-browser');
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        $browserTargets = self::detectBrowserTestTargets($projectRoot, $testPath);

        $globs = [
            'resources/js/** !*.php',
            'resources/css/** !*.php',
            'public/hot !*.php',
            'public/** !*.php',
        ];

        $patterns = [];

        foreach ($globs as $glob) {
            $patterns[$glob] = $browserTargets;
        }

        return $patterns;
    }

    /**
     * @return array<int, string>
     */
    public static function detectBrowserTestTargets(string $projectRoot, string $testPath): array
    {
        $targets = [];

        $candidate = $testPath.'/Browser';

        if (is_dir($projectRoot.DIRECTORY_SEPARATOR.$candidate)) {
            $targets[] = $candidate;
        }

        if (class_exists(BrowserTestIdentifier::class)) {
            $repo = TestSuite::getInstance()->tests;

            foreach ($repo->getFilenames() as $filename) {
                $factory = $repo->get($filename);

                if (! $factory instanceof TestCaseFactory) {
                    continue;
                }

                foreach ($factory->methods as $method) {
                    if (BrowserTestIdentifier::isBrowserTest($method)) {
                        $rel = self::fileRelative($projectRoot, $filename);

                        if ($rel !== null) {
                            $targets[] = $rel;
                        }

                        break;
                    }
                }
            }
        }

        return array_values(array_unique($targets));
    }

    private static function fileRelative(string $projectRoot, string $path): ?string
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
