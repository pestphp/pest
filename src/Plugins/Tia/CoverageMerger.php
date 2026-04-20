<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Throwable;

/**
 * Merges the current run's PHPUnit coverage into a cached full-suite
 * snapshot so `--tia --coverage` can produce a complete report after
 * executing only the affected tests.
 *
 * Invoked from `Pest\Support\Coverage::report()` right before the coverage
 * file is consumed. A marker file dropped by the `Tia` plugin gates the
 * behaviour — plain `--coverage` runs (no `--tia`) leave the marker absent
 * and therefore keep their existing semantics.
 *
 * Algorithm
 * ---------
 * The PHPUnit coverage PHP file unserialises to a `CodeCoverage` object.
 * Its `ProcessedCodeCoverageData` stores, per source file, per line, the
 * list of test IDs that covered that line. We:
 *
 *   1. Load the cached snapshot (from a previous `--tia --coverage` run).
 *   2. Strip every test id that re-ran this time from the cached map —
 *      the tests that ran now are the ones whose attribution is fresh.
 *   3. Merge the current run into the stripped cached snapshot via
 *      `CodeCoverage::merge()`.
 *   4. Write the merged result back to the report path (so Pest's report
 *      generator sees the full suite) and to the cache path (for the
 *      next invocation).
 *   5. Remove the marker so subsequent plain `--coverage` runs are
 *      untouched.
 *
 * If no cache exists yet (first `--tia --coverage` run on this machine)
 * we simply save the current file as the cache — nothing to merge yet.
 *
 * @internal
 */
final class CoverageMerger
{
    public static function applyIfMarked(string $reportPath): void
    {
        $markerPath = Tia::coverageMarkerPath();

        if (! is_file($markerPath)) {
            return;
        }

        @unlink($markerPath);

        $cachePath = Tia::coverageCachePath();

        if (! is_file($cachePath)) {
            // First `--tia --coverage` run: nothing cached yet, the current
            // report is the full suite itself. Save it verbatim so the next
            // run has a snapshot to merge against.
            @copy($reportPath, $cachePath);

            return;
        }

        try {
            /** @var CodeCoverage $cached */
            $cached = require $cachePath;

            /** @var CodeCoverage $current */
            $current = require $reportPath;
        } catch (Throwable) {
            // Corrupt cache or unreadable report — fall back to the plain
            // PHPUnit behaviour (the existing `require $reportPath` in the
            // caller still runs against the untouched file).
            return;
        }

        self::stripCurrentTestsFromCached($cached, $current);

        $cached->merge($current);

        // Serialise the merged object back using PHPUnit's own "return
        // expression" PHP format. Using `var_export` on the serialised
        // payload keeps the file self-contained and independent of
        // PHPUnit's internal exporter — the reader only needs to
        // `require` it back.
        $serialised = "<?php return unserialize(".var_export(serialize($cached), true).");\n";

        @file_put_contents($reportPath, $serialised);
        @file_put_contents($cachePath, $serialised);
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
                if ($ids === null || $ids === []) {
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
}
