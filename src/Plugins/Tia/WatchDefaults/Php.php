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
            // queue connections, etc. Not PHP, not fingerprinted. Covers
            // the local-override variants (`.env.local`, `.env.testing.local`)
            // that both Laravel and Symfony recommend for machine-specific
            // config.
            '.env' => [$testPath],
            '.env.testing' => [$testPath],
            '.env.local' => [$testPath],
            '.env.*.local' => [$testPath],

            // Docker / CI — can affect integration test infrastructure.
            'docker-compose.yml' => [$testPath],
            'docker-compose.yaml' => [$testPath],

            // PHPUnit / Pest config (XML) — phpunit.xml IS fingerprinted, but
            // phpunit.xml.dist and other XML overrides are not individually
            // tracked by the coverage driver.
            'phpunit.xml.dist' => [$testPath],

            // `tests/Pest.php` is loaded once per suite (during BootFiles)
            // so its `pest()->extend()`, `expect()->extend()`, helpers,
            // etc. execute outside the per-test coverage window — no
            // edge captures it. Watch-pattern broadcast triggers a
            // replay of every test (results refresh) without a full
            // record-mode graph rebuild.
            $testPath.'/Pest.php' => [$testPath],

            // Pest dataset definitions are loaded once at boot, outside
            // the per-test coverage window — no edge captures them. A
            // change to a shared dataset can flip the result of any test
            // that uses it, so broadcast every dataset edit to the full
            // suite.
            $testPath.'/Datasets/**/*.php' => [$testPath],

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
