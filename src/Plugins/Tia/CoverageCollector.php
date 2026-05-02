<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\Runner\CodeCoverage as PhpUnitCodeCoverage;
use Throwable;

/**
 * @internal
 */
final class CoverageCollector
{
    /**
     * @var array<string, string|null>
     */
    private array $classFileCache = [];

    /**
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

        assert(property_exists($className, '__filename') && is_string($className::$__filename));

        return $className::$__filename;
    }
}
