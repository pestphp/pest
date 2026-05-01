<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\TestSuite;
use ReflectionClass;

/**
 * Captures per-test file coverage. Singleton because PCOV/Xdebug have a single global state
 * shared across the `Prepared` and `Finished` subscribers.
 *
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

    // Source file → declared class names. Built incrementally as classes are autoloaded.
    // Used to walk the interface/trait/parent hierarchy which coverage drivers miss
    // (interfaces and empty traits emit no executable bytecode).
    /** @var array<string, list<string>> */
    private array $fileToClassNames = [];

    /** @var array<string, true> */
    private array $indexedClassNames = [];

    /** @var array<string, list<string>> */
    private array $classDependencyCache = [];

    /** @var array<string, list<string>> */
    private array $testImportFileCache = [];

    /** @var array<string, true> */
    private array $includedFilesAtTestStart = [];

    private bool $active = false;

    private bool $driverChecked = false;

    private bool $driverAvailable = false;

    private string $driver = 'none';

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
                // Probing with start/stop emits E_WARNING when coverage is off, which monitoring agents
                // (Sentry, Bugsnag) can surface as a real error. xdebug_info('mode') is silent.
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

    public function driver(): string
    {
        $this->driverAvailable();

        return $this->driver;
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
        $this->includedFilesAtTestStart = AutoloadEdges::snapshot();

        if ($this->classUsesDatabase($className)) {
            $this->perTestUsesDatabase[$file] = true;
        }

        // Walk parent-class chain to link ancestor files. Empty base classes (e.g. a trait-only
        // TestCase) emit no executable bytecode, so the coverage driver never records them.
        $this->linkAncestorFiles($className);
        $this->linkImportedFiles($file);

        if ($this->driver === 'pcov') {
            \pcov\clear();
            \pcov\start();

            return;
        }

        // Xdebug
        \xdebug_start_code_coverage();
    }

    public function endTest(): void
    {
        if (! $this->active || ! $this->driverAvailable() || $this->currentTestFile === null) {
            return;
        }

        if ($this->driver === 'pcov') {
            \pcov\stop();
            /** @var array<string, mixed> $data */
            $data = \pcov\collect(\pcov\all);

            // pcov returns every executable line in every file it
            // tracked: positive values for executed lines, `-1` for
            // executable-but-not-run. A file with no positives was
            // loaded but nothing in it ran during this test's window
            // — typically a declaration-only file (Mailables, Enums,
            // DTOs) pulled in by some service-provider's static `use`
            // at framework boot. Including those attributes every
            // globally-bootstrapped class to whichever test triggered
            // the boot, blowing up the affected set on edits to those
            // files.
            $coveredFiles = self::filesWithExecutedLines($data);
        } else {
            /** @var array<string, mixed> $data */
            $data = \xdebug_get_code_coverage();
            // `true` resets Xdebug's buffer; without it the next start() accumulates prior test coverage.
            \xdebug_stop_code_coverage(true);

            $coveredFiles = array_keys($data);
        }

        foreach ($coveredFiles as $sourceFile) {
            $this->perTestFiles[$this->currentTestFile][$sourceFile] = true;
        }

        foreach (AutoloadEdges::newProjectFiles(
            $this->includedFilesAtTestStart,
            AutoloadEdges::snapshot(),
            TestSuite::getInstance()->rootPath,
            $this->currentTestFile,
        ) as $sourceFile) {
            $this->perTestFiles[$this->currentTestFile][$sourceFile] = true;
        }

        // Walk covered classes' interfaces/traits/parents. Interfaces have no executable bytecode,
        // so a signature change would leave implementing-class tests stale without this walk.
        $this->linkSourceDependencies($coveredFiles);

        $this->currentTestFile = null;
        $this->includedFilesAtTestStart = [];
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

    /** @param  iterable<int, string>  $sourceFiles */
    public function linkSourcesForTest(string $testFile, iterable $sourceFiles): void
    {
        if (! $this->active) {
            return;
        }

        if ($testFile === '') {
            return;
        }

        foreach ($sourceFiles as $sourceFile) {
            if ($sourceFile === '') {
                continue;
            }

            $this->perTestFiles[$testFile][$sourceFile] = true;
        }
    }

    /** @param  array<int, string>  $coveredFiles */
    private function linkSourceDependencies(array $coveredFiles): void
    {
        if ($this->currentTestFile === null) {
            return;
        }

        $this->refreshClassMap();

        foreach ($coveredFiles as $coveredFile) {
            if (! isset($this->fileToClassNames[$coveredFile])) {
                continue;
            }

            foreach ($this->fileToClassNames[$coveredFile] as $name) {
                foreach ($this->classDependencies($name) as $depFile) {
                    $this->perTestFiles[$this->currentTestFile][$depFile] = true;
                }
            }
        }
    }

    private function refreshClassMap(): void
    {
        $names = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits(),
        );

        foreach ($names as $name) {
            if (isset($this->indexedClassNames[$name])) {
                continue;
            }
            $this->indexedClassNames[$name] = true;

            if (! class_exists($name, false)
                && ! interface_exists($name, false)
                && ! trait_exists($name, false)) {
                continue;
            }

            $reflection = new ReflectionClass($name);

            if ($reflection->isInternal()) {
                continue;
            }

            $file = $reflection->getFileName();

            if (! is_string($file)) {
                continue;
            }

            if (str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $this->fileToClassNames[$file][] = $name;
        }
    }

    /** @return list<string> */
    private function classDependencies(string $className): array
    {
        if (isset($this->classDependencyCache[$className])) {
            return $this->classDependencyCache[$className];
        }

        if (! class_exists($className, false)
            && ! interface_exists($className, false)
            && ! trait_exists($className, false)) {
            return $this->classDependencyCache[$className] = [];
        }

        $reflection = new ReflectionClass($className);

        $files = [];

        $linkSymbol = static function (string $name) use (&$files): void {
            if (! class_exists($name, false)
                && ! interface_exists($name, false)
                && ! trait_exists($name, false)) {
                return;
            }
            $r = new ReflectionClass($name);
            if ($r->isInternal()) {
                return;
            }
            $f = $r->getFileName();
            if (! is_string($f) || str_contains($f, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                return;
            }
            $files[$f] = true;
        };

        // getInterfaceNames() is transitive — includes parents' interfaces — so one pass suffices.
        foreach ($reflection->getInterfaceNames() as $iname) {
            $linkSymbol($iname);
        }

        foreach ($reflection->getTraitNames() as $tname) {
            $linkSymbol($tname);
        }

        $parent = $reflection->getParentClass();
        while ($parent !== false && ! $parent->isInternal()) {
            $f = $parent->getFileName();
            if (is_string($f) && ! str_contains($f, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                $files[$f] = true;
            }
            foreach ($parent->getTraitNames() as $tname) {
                $linkSymbol($tname);
            }
            $parent = $parent->getParentClass();
        }

        return $this->classDependencyCache[$className] = array_keys($files);
    }

    private function linkAncestorFiles(string $className): void
    {
        if (! class_exists($className, false)) {
            return;
        }

        $reflection = new ReflectionClass($className);
        $parent = $reflection->getParentClass();

        while ($parent !== false) {
            if ($parent->isInternal()) {
                break;
            }

            $file = $parent->getFileName();

            if (is_string($file) && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                $this->perTestFiles[(string) $this->currentTestFile][$file] = true;
            }

            $parent = $parent->getParentClass();
        }
    }

    private function linkImportedFiles(string $testFile): void
    {
        if ($this->currentTestFile === null) {
            return;
        }

        foreach ($this->importedFilesFor($testFile) as $file) {
            $this->perTestFiles[$this->currentTestFile][$file] = true;
        }
    }

    /**
     * @return list<string>
     */
    private function importedFilesFor(string $testFile): array
    {
        if (array_key_exists($testFile, $this->testImportFileCache)) {
            return $this->testImportFileCache[$testFile];
        }

        $source = @file_get_contents($testFile);
        if ($source === false) {
            return $this->testImportFileCache[$testFile] = [];
        }

        $files = [];

        foreach ($this->importedClassNames($source) as $className) {
            $file = $this->findAutoloadFile($className);

            if ($file !== null && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                $files[$file] = true;
            }
        }

        return $this->testImportFileCache[$testFile] = array_keys($files);
    }

    /**
     * @return list<string>
     */
    private function importedClassNames(string $source): array
    {
        preg_match_all('/^use\s+(?!function\s|const\s)([^;]+);/mi', $source, $matches);

        $classes = [];

        foreach ($matches[1] as $import) {
            $import = trim($import);

            if ($import === '') {
                continue;
            }

            $open = strpos($import, '{');
            $close = strrpos($import, '}');

            if ($open !== false && $close !== false && $close > $open) {
                $prefix = trim(trim(substr($import, 0, $open)), '\\');
                $items = explode(',', substr($import, $open + 1, $close - $open - 1));

                foreach ($items as $item) {
                    $class = $this->normaliseImportedClass($prefix.'\\'.trim($item));

                    if ($class !== null) {
                        $classes[$class] = true;
                    }
                }

                continue;
            }

            $class = $this->normaliseImportedClass($import);

            if ($class !== null) {
                $classes[$class] = true;
            }
        }

        return array_keys($classes);
    }

    private function normaliseImportedClass(string $import): ?string
    {
        $import = trim(trim($import), '\\');

        if ($import === '') {
            return null;
        }

        $parts = preg_split('/\s+as\s+/i', $import);
        if ($parts === false || $parts === []) {
            return null;
        }

        $class = trim(trim($parts[0]), '\\');

        return $class === '' ? null : $class;
    }

    private function findAutoloadFile(string $className): ?string
    {
        foreach (spl_autoload_functions() as $loader) {
            if (! is_array($loader) || ! isset($loader[0]) || ! is_object($loader[0])) {
                continue;
            }

            if (! method_exists($loader[0], 'findFile')) {
                continue;
            }

            /** @var mixed $file */
            $file = $loader[0]->findFile($className);

            if (is_string($file) && $file !== '') {
                $real = @realpath($file);

                return $real === false ? $file : $real;
            }
        }

        return null;
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

    // Prefers Pest's `$__filename` static (the original .php file) over ReflectionClass::getFileName()
    // (which returns the trait file for methods brought in via `uses SharedTestBehavior`).
    private function readPestFilename(string $className): ?string
    {
        if (! class_exists($className, false)) {
            return null;
        }

        $reflection = new ReflectionClass($className);

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

    /**
     * Filters pcov's `file => line => executionCount` map to the files
     * that actually had at least one executed line. pcov reports `-1`
     * for "executable but not run" and a positive count for executed
     * lines; a file with no positives was loaded but contributed no
     * executed code to this test.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function filesWithExecutedLines(array $data): array
    {
        $out = [];

        foreach ($data as $file => $lines) {
            if (! is_string($file) || ! is_array($lines)) {
                continue;
            }

            foreach ($lines as $count) {
                if (is_int($count) && $count > 0) {
                    $out[] = $file;

                    continue 2;
                }
            }
        }

        return $out;
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
        $this->fileToClassNames = [];
        $this->indexedClassNames = [];
        $this->classDependencyCache = [];
        $this->active = false;
    }
}
