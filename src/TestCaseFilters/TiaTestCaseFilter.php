<?php

declare(strict_types=1);

namespace Pest\TestCaseFilters;

use Pest\Contracts\TestCaseFilter;
use Pest\Plugins\Tia\Graph;

/**
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
