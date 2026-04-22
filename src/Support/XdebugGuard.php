<?php

declare(strict_types=1);

namespace Pest\Support;

use Composer\XdebugHandler\XdebugHandler;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Storage;

/**
 * Re-execs the PHP process without Xdebug on TIA replay runs, matching the
 * behaviour of composer, phpstan, rector, psalm and pint.
 *
 * Xdebug imposes a 30–50% runtime tax on every PHP process that loads it —
 * even when nothing is actively tracing, profiling or breaking. Plain `pest`
 * users might rely on Xdebug being loaded (IDE breakpoints, step-through
 * debugging, custom tooling), so we intentionally leave non-TIA runs alone.
 *
 * The guard engages only when ALL of these hold:
 *   1. `--tia` is present in argv.
 *   2. No `--fresh` flag (forced record always drives the coverage
 *      driver; dropping Xdebug would break the recording).
 *   3. No `--coverage*` flag (coverage runs need the driver regardless).
 *   4. A valid graph already exists on disk AND its structural fingerprint
 *      matches the current environment — i.e. TIA will replay rather than
 *      record. Record runs need the driver.
 *   5. Xdebug's configured mode is either empty or exactly `['coverage']`.
 *      Any other mode (debug, develop, trace, profile, gcstats) signals the
 *      user wants Xdebug for reasons unrelated to coverage, so we leave it
 *      alone even on replay.
 *
 * `PEST_ALLOW_XDEBUG=1` remains an explicit manual override; it is honoured
 * natively by `composer/xdebug-handler`.
 *
 * @internal
 */
final class XdebugGuard
{
    /**
     * Call as early as possible after composer autoload, before any Pest
     * class beyond the autoloader is touched. Safe when Xdebug is not
     * loaded (returns immediately) and when `composer/xdebug-handler` is
     * unavailable (defensive `class_exists` check).
     */
    public static function maybeDrop(string $projectRoot): void
    {
        if (! class_exists(XdebugHandler::class)) {
            return;
        }

        if (! extension_loaded('xdebug')) {
            return;
        }

        if (! self::xdebugIsCoverageOnly()) {
            return;
        }

        $argv = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];

        if (! self::runLooksDroppable($argv, $projectRoot)) {
            return;
        }

        (new XdebugHandler('pest'))->check();
    }

    /**
     * True when Xdebug 3+ is running in coverage-only mode (or empty). False
     * for older Xdebug without `xdebug_info` — be conservative and leave it
     * loaded; we can't prove the mode is safe to drop.
     */
    private static function xdebugIsCoverageOnly(): bool
    {
        if (! function_exists('xdebug_info')) {
            return false;
        }

        $modes = @xdebug_info('mode');

        if (! is_array($modes)) {
            return false;
        }

        $modes = array_values(array_filter($modes, is_string(...)));

        if ($modes === []) {
            return true;
        }

        return $modes === ['coverage'];
    }

    /**
     * Encodes the argv-based rules: `--tia` must be present, no coverage
     * flag, no forced rebuild, and TIA must be about to replay rather than
     * record. Plain `pest` (and anything else without `--tia`) keeps Xdebug
     * loaded so non-TIA users aren't surprised by behaviour changes.
     *
     * @param  array<int, mixed>  $argv
     */
    private static function runLooksDroppable(array $argv, string $projectRoot): bool
    {
        $hasTia = false;

        foreach ($argv as $value) {
            if (! is_string($value)) {
                continue;
            }

            if ($value === '--coverage'
                || str_starts_with($value, '--coverage=')
                || str_starts_with($value, '--coverage-')) {
                return false;
            }

            if ($value === '--fresh') {
                return false;
            }

            if ($value === '--tia') {
                $hasTia = true;
            }
        }

        if (! $hasTia) {
            return false;
        }

        return self::tiaWillReplay($projectRoot);
    }

    /**
     * True when a valid TIA graph already lives on disk AND its structural
     * fingerprint matches the current environment. Any other outcome
     * (missing graph, unreadable JSON, structural drift) means TIA will
     * record and the driver must stay loaded.
     */
    private static function tiaWillReplay(string $projectRoot): bool
    {
        $path = self::graphPath($projectRoot);

        if (! is_file($path)) {
            return false;
        }

        $json = @file_get_contents($path);

        if ($json === false) {
            return false;
        }

        $graph = Graph::decode($json, $projectRoot);

        if (! $graph instanceof Graph) {
            return false;
        }

        return Fingerprint::structuralMatches(
            $graph->fingerprint(),
            Fingerprint::compute($projectRoot),
        );
    }

    /**
     * On-disk location of the TIA graph — delegates to {@see Storage} so
     * the writer (TIA's bootstrapper) and this reader stay in sync
     * without a runtime container lookup (the container isn't booted yet
     * at this point).
     */
    private static function graphPath(string $projectRoot): string
    {
        return Storage::tempDir($projectRoot).DIRECTORY_SEPARATOR.Tia::KEY_GRAPH;
    }
}
