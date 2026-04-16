<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

/**
 * Baseline watch patterns for any PHP project.
 *
 * @internal
 */
final readonly class Php implements WatchDefault
{
    public function applicable(): bool
    {
        return true;
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        // NOTE: composer.json / composer.lock changes are caught by the
        // fingerprint (which hashes composer.lock). PHP files are tracked by
        // the coverage driver. Only non-PHP, non-fingerprinted files that
        // can silently alter test behaviour belong here.

        return [
            // Environment files — can change DB drivers, feature flags,
            // queue connections, etc. Not PHP, not fingerprinted.
            '.env' => [$testPath],
            '.env.testing' => [$testPath],

            // Docker / CI — can affect integration test infrastructure.
            'docker-compose.yml' => [$testPath],
            'docker-compose.yaml' => [$testPath],

            // PHPUnit / Pest config (XML) — phpunit.xml IS fingerprinted, but
            // phpunit.xml.dist and other XML overrides are not individually
            // tracked by the coverage driver.
            'phpunit.xml.dist' => [$testPath],

            // Test fixtures — JSON, CSV, XML, TXT data files consumed by
            // assertions. A fixture change can flip a test result.
            $testPath.'/Fixtures/**/*.json' => [$testPath],
            $testPath.'/Fixtures/**/*.csv' => [$testPath],
            $testPath.'/Fixtures/**/*.xml' => [$testPath],
            $testPath.'/Fixtures/**/*.txt' => [$testPath],

            // Pest snapshots — external edits to snapshot files invalidate
            // snapshot assertions.
            $testPath.'/.pest/snapshots/**/*.snap' => [$testPath],
        ];
    }
}
