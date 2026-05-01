<?php

declare(strict_types=1);

namespace Pest\Restarters;

use Composer\XdebugHandler\XdebugHandler;
use Pest\Contracts\Restarter;
use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Storage;

/**
 * @internal
 */
final class XdebugRestarter implements Restarter
{
    /**
     * @param  array<int, string>  $arguments
     */
    public function maybeRestart(string $projectRoot, array $arguments): void
    {
        if (! class_exists(XdebugHandler::class)) {
            return;
        }

        if (! extension_loaded('xdebug')) {
            return;
        }

        if (! $this->xdebugIsCoverageOnly()) {
            return;
        }

        if (! $this->runLooksDroppable($arguments, $projectRoot)) {
            return;
        }

        (new XdebugHandler('pest'))->check();
    }

    /**
     * True when Xdebug 3+ is running in coverage-only mode (or empty). False
     * for older Xdebug without `xdebug_info` — be conservative and leave it
     * loaded; we can't prove the mode is safe to drop.
     */
    private function xdebugIsCoverageOnly(): bool
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
     * TIA must be enabled for this run, no coverage flag, no forced
     * rebuild, and TIA must be about to replay rather than record. Plain
     * `pest` (and anything else without TIA enabled) keeps Xdebug loaded
     * so non-TIA users aren't surprised by behaviour changes.
     *
     * @param  array<int, string>  $arguments
     */
    private function runLooksDroppable(array $arguments, string $projectRoot): bool
    {
        foreach ($arguments as $value) {
            if ($value === '--coverage'
                || str_starts_with($value, '--coverage=')
                || str_starts_with($value, '--coverage-')) {
                return false;
            }

            if ($value === '--fresh') {
                return false;
            }
        }

        if (! Tia::isEnabledForRun($arguments)) {
            return false;
        }

        return $this->tiaWillReplay($projectRoot);
    }

    /**
     * True when a valid TIA graph already lives on disk AND its structural
     * fingerprint matches the current environment. Any other outcome
     * (missing graph, unreadable JSON, structural drift) means TIA will
     * record and the driver must stay loaded.
     */
    private function tiaWillReplay(string $projectRoot): bool
    {
        $path = Storage::tempDir($projectRoot).DIRECTORY_SEPARATOR.Tia::KEY_GRAPH;

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
}
