<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Contracts\TestCaseFilter;
use Pest\Plugins\Tia\Graph;

/**
 * Accepts a test file in one of three cases:
 *
 *   1. The file falls outside the project root (we cannot reason about it, so
 *      stay safe and run it).
 *   2. The graph has no record of the file — this is a new test that was
 *      never part of a recording run, so we accept it by default. Skipping
 *      unknown tests would be a correctness hazard (developers add tests and
 *      TIA would silently not run them).
 *   3. The graph knows the file AND it is in the affected set.
 *
 * @internal
 */
final readonly class TiaTestCaseFilter implements TestCaseFilter
{
    /**
     * @param  array<string, true>  $affectedTestFiles  Keys are project-relative test file paths.
     */
    public function __construct(
        private string $projectRoot,
        private Graph $graph,
        private array $affectedTestFiles,
    ) {}

    public function accept(string $testCaseFilename): bool
    {
        $rel = $this->relative($testCaseFilename);

        if ($rel === null) {
            return true;
        }

        if (! $this->graph->knowsTest($rel)) {
            return true;
        }

        return isset($this->affectedTestFiles[$rel]);
    }

    private function relative(string $path): ?string
    {
        $real = @realpath($path);

        if ($real === false) {
            $real = $path;
        }

        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($real, $root)) {
            return null;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root)));
    }
}
