<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Throwable;

/**
 * Merges the current run's PHPUnit coverage into a cached full-suite
 * snapshot so `--tia --coverage` can produce a complete report after
 * executing only the affected tests.
 *
 * Invoked from `Pest\Support\Coverage::report()` right before the coverage
 * file is consumed. A marker dropped by the `Tia` plugin gates the
 * behaviour — plain `--coverage` runs (no `--tia`) leave the marker absent
 * and therefore keep their existing semantics.
 *
 * Algorithm
 * ---------
 * The PHPUnit coverage PHP file unserialises to a `CodeCoverage` object.
 * Its `ProcessedCodeCoverageData` stores, per source file, per line, the
 * list of test IDs that covered that line. We:
 *
 *   1. Load the cached snapshot from `State` (serialised bytes).
 *   2. Strip every test id that re-ran this time from the cached map —
 *      the tests that ran now are the ones whose attribution is fresh.
 *   3. Merge the current run into the stripped cached snapshot via
 *      `CodeCoverage::merge()`.
 *   4. Write the merged result back to the report path (so Pest's report
 *      generator sees the full suite) and back into `State` (for the
 *      next invocation).
 *
 * If no cache exists yet (first `--tia --coverage` run on this machine)
 * we serialise the current object and save it — nothing to merge yet.
 *
 * @internal
 */
final class CoverageMerger
{
    public static function applyIfMarked(string $reportPath): void
    {
        $state = self::state();

        if (! $state instanceof State || ! $state->exists(Tia::KEY_COVERAGE_MARKER)) {
            return;
        }

        $state->delete(Tia::KEY_COVERAGE_MARKER);

        $cachedBytes = $state->read(Tia::KEY_COVERAGE_CACHE);

        if ($cachedBytes === null) {
            // First `--tia --coverage` run: nothing cached yet, so the
            // current file already represents the full suite. Capture it
            // verbatim (as serialised bytes) for next time.
            $current = self::requireCoverage($reportPath);

            if ($current instanceof CodeCoverage) {
                $state->write(Tia::KEY_COVERAGE_CACHE, serialize($current));
            }

            return;
        }

        $cached = self::unserializeCoverage($cachedBytes);
        $current = self::requireCoverage($reportPath);

        if (! $cached instanceof CodeCoverage || ! $current instanceof CodeCoverage) {
            return;
        }

        self::stripCurrentTestsFromCached($cached, $current);

        $cached->merge($current);

        $serialised = serialize($cached);

        // Write back to the PHPUnit-style `.cov` path so the report reader
        // can `require` it, and to the state cache for the next run.
        @file_put_contents(
            $reportPath,
            '<?php return unserialize('.var_export($serialised, true).");\n",
        );
        $state->write(Tia::KEY_COVERAGE_CACHE, $serialised);
    }

    /**
     * Removes from `$cached`'s per-line test attribution any test id that
     * appears in `$current`. Those tests just ran, so the fresh slice is
     * authoritative — keeping stale attribution in the cache would claim
     * a test still covers a line it no longer touches.
     */
    private static function stripCurrentTestsFromCached(CodeCoverage $cached, CodeCoverage $current): void
    {
        $currentIds = self::collectTestIds($current);

        if ($currentIds === []) {
            return;
        }

        $cachedData = $cached->getData();
        $lineCoverage = $cachedData->lineCoverage();

        foreach ($lineCoverage as $file => $lines) {
            foreach ($lines as $line => $ids) {
                if ($ids === null) {
                    continue;
                }
                if ($ids === []) {
                    continue;
                }
                $filtered = array_values(array_diff($ids, $currentIds));

                if ($filtered !== $ids) {
                    $lineCoverage[$file][$line] = $filtered;
                }
            }
        }

        $cachedData->setLineCoverage($lineCoverage);
    }

    /**
     * @return array<int, string>
     */
    private static function collectTestIds(CodeCoverage $coverage): array
    {
        $ids = [];

        foreach ($coverage->getData()->lineCoverage() as $lines) {
            foreach ($lines as $hits) {
                if ($hits === null) {
                    continue;
                }

                foreach ($hits as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private static function state(): ?State
    {
        try {
            $state = Container::getInstance()->get(State::class);
        } catch (Throwable) {
            return null;
        }

        return $state instanceof State ? $state : null;
    }

    private static function requireCoverage(string $reportPath): ?CodeCoverage
    {
        if (! is_file($reportPath)) {
            return null;
        }

        try {
            /** @var mixed $value */
            $value = require $reportPath;
        } catch (Throwable) {
            return null;
        }

        return $value instanceof CodeCoverage ? $value : null;
    }

    private static function unserializeCoverage(string $bytes): ?CodeCoverage
    {
        try {
            $value = @unserialize($bytes);
        } catch (Throwable) {
            return null;
        }

        return $value instanceof CodeCoverage ? $value : null;
    }
}
