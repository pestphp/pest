<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\Runner\CodeCoverage as PhpUnitCodeCoverage;
use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class CoverageCollector
{
    /**
     * Cached `className → test file` lookups. Class reflection is cheap
     * individually but the record run can visit tens of thousands of
     * samples, so the cache matters.
     *
     * @var array<string, string|null>
     */
    private array $classFileCache = [];

    /**
     * Rebuilds the same `absolute test file → list<absolute source file>`
     * shape that `Recorder::perTestFiles()` exposes, so callers can treat
     * the two collectors interchangeably when feeding the graph.
     *
     * @return array<string, array<int, string>>
     */
    public function perTestFiles(): array
    {
        if (! PhpUnitCodeCoverage::instance()->isActive()) {
            return [];
        }

        try {
            $lineCoverage = PhpUnitCodeCoverage::instance()
                ->codeCoverage()
                ->getData()
                ->lineCoverage();
        } catch (Throwable) {
            return [];
        }

        /** @var array<string, array<string, true>> $edges */
        $edges = [];

        foreach ($lineCoverage as $sourceFile => $lines) {
            // Collect the set of tests that hit any line in this file once,
            // then emit one edge per (testFile, sourceFile) pair. Walking
            // the lines per test would re-resolve the test file repeatedly.
            $testIds = [];

            foreach ($lines as $hits) {
                if ($hits === null) {
                    continue;
                }

                foreach ($hits as $id) {
                    $testIds[$id] = true;
                }
            }

            foreach (array_keys($testIds) as $testId) {
                $testFile = $this->testIdToFile($testId);

                if ($testFile === null) {
                    continue;
                }

                $edges[$testFile][$sourceFile] = true;
            }
        }

        $out = [];

        foreach ($edges as $testFile => $sources) {
            $out[$testFile] = array_keys($sources);
        }

        return $out;
    }

    public function reset(): void
    {
        $this->classFileCache = [];
    }

    private function testIdToFile(string $testId): ?string
    {
        // PHPUnit's test id is `ClassName::methodName` with an optional
        // `#dataSetName` suffix for data-provider runs. Strip the dataset
        // part — we only need the class.
        $hash = strpos($testId, '#');
        $identifier = $hash === false ? $testId : substr($testId, 0, $hash);

        if (! str_contains($identifier, '::')) {
            return null;
        }

        [$className] = explode('::', $identifier, 2);

        if (array_key_exists($className, $this->classFileCache)) {
            return $this->classFileCache[$className];
        }

        $file = $this->resolveClassFile($className);
        $this->classFileCache[$className] = $file;

        return $file;
    }

    private function resolveClassFile(string $className): ?string
    {
        if (! class_exists($className, false)) {
            return null;
        }

        $reflection = new ReflectionClass($className);

        // Pest's eval'd test classes expose the original `.php` path on a
        // static `$__filename`. The eval'd class itself has no file of its
        // own, so prefer this property when present.
        if ($reflection->hasProperty('__filename')) {
            $property = $reflection->getProperty('__filename');

            if ($property->isStatic()) {
                $value = $property->getValue();

                if (is_string($value)) {
                    return $value;
                }
            }
        }

        $file = $reflection->getFileName();

        return is_string($file) ? $file : null;
    }
}
