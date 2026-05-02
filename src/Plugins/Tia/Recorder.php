<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\TestSuite;
use ReflectionClass;

/**
 * @internal
 */
final class Recorder
{
    private ?string $currentTestFile = null;

    /** @var array<string, array<string, true>> */
    private array $perTestFiles = [];

    /** @var array<string, array<string, true>> */
    private array $perTestTables = [];

    /** @var array<string, array<string, true>> */
    private array $perTestInertiaComponents = [];

    /** @var array<string, true> */
    private array $perTestUsesDatabase = [];

    /** @var array<string, string|null> */
    private array $classFileCache = [];

    /** @var array<string, bool> */
    private array $classUsesDatabaseCache = [];

    private bool $active = false;

    private bool $driverChecked = false;

    private bool $driverAvailable = false;

    private string $driver = 'none';

    private ?SourceScope $sourceScope = null;

    public function activate(): void
    {
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function driverAvailable(): bool
    {
        if (! $this->driverChecked) {
            if (function_exists('pcov\\start')) {
                $this->driver = 'pcov';
                $this->driverAvailable = true;
            } elseif (function_exists('xdebug_start_code_coverage') && function_exists('xdebug_info')) {
                $modes = \xdebug_info('mode');

                if (is_array($modes) && in_array('coverage', $modes, true)) {
                    $this->driver = 'xdebug';
                    $this->driverAvailable = true;
                }
            }

            $this->driverChecked = true;
        }

        return $this->driverAvailable;
    }

    public function beginTest(string $className, string $methodName, string $fallbackFile): void
    {
        if (! $this->active || ! $this->driverAvailable()) {
            return;
        }

        if ($this->currentTestFile !== null) {
            return;
        }

        $file = $this->resolveTestFile($className, $fallbackFile);

        if ($file === null) {
            return;
        }

        $this->currentTestFile = $file;

        if ($this->classUsesDatabase($className)) {
            $this->perTestUsesDatabase[$file] = true;
        }

        if ($this->driver === 'pcov') {
            \pcov\clear();
            \pcov\start();

            return;
        }

        \xdebug_start_code_coverage();
    }

    public function endTest(): void
    {
        if (! $this->active || ! $this->driverAvailable() || $this->currentTestFile === null) {
            return;
        }

        if ($this->driver === 'pcov') {
            \pcov\stop();

            $scope = $this->sourceScope();
            $filesToCollectCoverageFor = [];

            foreach (\pcov\waiting() as $file) {
                if (is_string($file) && $scope->contains($file)) {
                    $filesToCollectCoverageFor[] = $file;
                }
            }

            /** @var array<string, mixed> $data */
            $data = \pcov\collect(\pcov\inclusive, $filesToCollectCoverageFor);

            $coveredFiles = $this->filesWithExecutedLines($data);
        } else {
            /** @var array<string, mixed> $data */
            $data = \xdebug_get_code_coverage();
            \xdebug_stop_code_coverage(true);

            $coveredFiles = array_keys($data);
        }

        foreach ($coveredFiles as $sourceFile) {
            $this->perTestFiles[$this->currentTestFile][$sourceFile] = true;
        }

        $this->currentTestFile = null;
    }

    public function linkSource(string $sourceFile): void
    {
        if (! $this->active) {
            return;
        }

        if ($this->currentTestFile === null) {
            return;
        }

        if ($sourceFile === '') {
            return;
        }

        $this->perTestFiles[$this->currentTestFile][$sourceFile] = true;
    }

    private function classUsesDatabase(string $className): bool
    {
        if (array_key_exists($className, $this->classUsesDatabaseCache)) {
            return $this->classUsesDatabaseCache[$className];
        }

        if (! class_exists($className, false)) {
            return $this->classUsesDatabaseCache[$className] = false;
        }

        static $needles = [
            'Illuminate\\Foundation\\Testing\\RefreshDatabase' => true,
            'Illuminate\\Foundation\\Testing\\DatabaseMigrations' => true,
            'Illuminate\\Foundation\\Testing\\DatabaseTransactions' => true,
        ];

        $reflection = new ReflectionClass($className);

        do {
            foreach (array_keys($reflection->getTraits()) as $traitName) {
                if (isset($needles[$traitName])) {
                    return $this->classUsesDatabaseCache[$className] = true;
                }
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false && ! $reflection->isInternal());

        return $this->classUsesDatabaseCache[$className] = false;
    }

    public function linkTable(string $table): void
    {
        if (! $this->active) {
            return;
        }

        if ($this->currentTestFile === null) {
            return;
        }

        if ($table === '') {
            return;
        }

        $this->perTestTables[$this->currentTestFile][strtolower($table)] = true;
    }

    public function linkInertiaComponent(string $component): void
    {
        if (! $this->active) {
            return;
        }

        if ($this->currentTestFile === null) {
            return;
        }

        if ($component === '') {
            return;
        }

        $this->perTestInertiaComponents[$this->currentTestFile][$component] = true;
    }

    /** @return array<string, array<int, string>> */
    public function perTestFiles(): array
    {
        $out = [];

        foreach ($this->perTestFiles as $testFile => $sources) {
            $out[$testFile] = array_keys($sources);
        }

        return $out;
    }

    /** @return array<string, array<int, string>> */
    public function perTestTables(): array
    {
        $out = [];

        foreach ($this->perTestTables as $testFile => $tables) {
            $names = array_keys($tables);
            sort($names);
            $out[$testFile] = $names;
        }

        return $out;
    }

    /** @return array<string, array<int, string>> */
    public function perTestInertiaComponents(): array
    {
        $out = [];

        foreach ($this->perTestInertiaComponents as $testFile => $components) {
            $names = array_keys($components);
            sort($names);
            $out[$testFile] = $names;
        }

        return $out;
    }

    /** @return array<string, true> */
    public function perTestUsesDatabase(): array
    {
        return $this->perTestUsesDatabase;
    }

    private function resolveTestFile(string $className, string $fallbackFile): ?string
    {
        if (array_key_exists($className, $this->classFileCache)) {
            $file = $this->classFileCache[$className];
        } else {
            $file = $this->readPestFilename($className);
            $this->classFileCache[$className] = $file;
        }

        if ($file !== null) {
            return $file;
        }

        if ($fallbackFile !== '' && $fallbackFile !== 'unknown' && ! str_contains($fallbackFile, "eval()'d")) {
            return $fallbackFile;
        }

        return null;
    }

    private function readPestFilename(string $className): ?string
    {
        if (! class_exists($className, false)) {
            return null;
        }

        assert(property_exists($className, '__filename') && is_string($className::$__filename));

        return $className::$__filename;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function filesWithExecutedLines(array $data): array
    {
        $out = [];

        foreach ($data as $file => $lines) {
            if (! is_array($lines)) {
                continue;
            }
            $covered = [];
            foreach ($lines as $line => $count) {
                if (is_int($count) && $count > 0) {
                    $covered[] = $line;
                }
            }

            if ($covered === []) {
                continue;
            }

            $lineKeys = array_keys($lines);
            if ($lineKeys !== [] && count($covered) === 1 && $covered[0] === max($lineKeys)) {
                continue;
            }

            $out[] = $file;
        }

        return $out;
    }

    private function sourceScope(): SourceScope
    {
        return $this->sourceScope ??= SourceScope::fromProjectRoot(TestSuite::getInstance()->rootPath);
    }

    public function reset(): void
    {
        $this->currentTestFile = null;
        $this->perTestFiles = [];
        $this->perTestTables = [];
        $this->perTestInertiaComponents = [];
        $this->perTestUsesDatabase = [];
        $this->classFileCache = [];
        $this->classUsesDatabaseCache = [];
        $this->sourceScope = null;
        $this->active = false;
    }
}
