<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\TestSuite;
use ReflectionClass;

/**
 * Captures per-test file coverage using the PCOV driver.
 *
 * Acts as a singleton because PCOV has a single global collection state and
 * the recorder is wired into PHPUnit through two distinct subscribers
 * (`Prepared` / `Finished`) that must share context.
 *
 * @internal
 */
final class Recorder
{
    /**
     * Test file currently being recorded, or `null` when idle.
     */
    private ?string $currentTestFile = null;

    /**
     * Aggregated map: absolute test file → set<absolute source file>.
     *
     * @var array<string, array<string, true>>
     */
    private array $perTestFiles = [];

    /**
     * Aggregated map: absolute test file → set<lowercase table name>.
     * Populated by `TableTracker` from `DB::listen` callbacks; consumed
     * at record finalize to populate the graph's `$testTables` edges
     * that drive migration-change impact analysis.
     *
     * @var array<string, array<string, true>>
     */
    private array $perTestTables = [];

    /**
     * Aggregated map: absolute test file → set<Inertia component name>.
     * Populated by `InertiaEdges` from Inertia responses observed at
     * request-handled time; consumed at record finalize to populate
     * the graph's per-test component edges that drive Vue / React
     * page-file impact analysis.
     *
     * @var array<string, array<string, true>>
     */
    private array $perTestInertiaComponents = [];

    /**
     * Set of absolute test files whose class hierarchy uses one of
     * Laravel's database-resetting traits (`RefreshDatabase`,
     * `DatabaseMigrations`, `DatabaseTransactions`). Captured at
     * `beginTest` so the finalize path can augment their table edges
     * even when seeders / pre-test DML fired before `TableTracker`
     * armed.
     *
     * @var array<string, true>
     */
    private array $perTestUsesDatabase = [];

    /**
     * Cached class → test file resolution.
     *
     * @var array<string, string|null>
     */
    private array $classFileCache = [];

    /**
     * Cached class → "uses Laravel DB trait" introspection result.
     *
     * @var array<string, bool>
     */
    private array $classUsesDatabaseCache = [];

    /**
     * Reverse map of project-local source file → list of class /
     * interface / trait names declared in it. Built incrementally as
     * tests run and new classes get autoloaded; consumed by
     * `linkSourceDependencies()` so a test's covered file's
     * declared classes can be walked for their interfaces, traits,
     * and parents (which the coverage driver doesn't capture
     * because interface declarations and empty traits emit no
     * executable bytecode).
     *
     * @var array<string, list<string>>
     */
    private array $fileToClassNames = [];

    /**
     * Names already folded into `$fileToClassNames`. Lets the
     * incremental refresher skip classes seen in a previous test.
     *
     * @var array<string, true>
     */
    private array $indexedClassNames = [];

    /**
     * Cached "files this class transitively depends on (interfaces,
     * traits, parent chain, parents' interfaces and traits)" for
     * project-local class names. Avoids re-walking the same
     * hierarchy on every test that touches the same class.
     *
     * @var array<string, list<string>>
     */
    private array $classDependencyCache = [];

    /**
     * Cached test-file import resolution.
     *
     * @var array<string, list<string>>
     */
    private array $testImportFileCache = [];

    /**
     * Included-file snapshot captured at the start of the current test.
     *
     * @var array<string, true>
     */
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
                // Xdebug 3+ exposes the active mode set via `xdebug_info`,
                // so we can ask directly instead of probing with a
                // start/stop pair. The probe approach used to emit
                // E_WARNING when coverage mode was off; with monitoring
                // agents (Sentry, Bugsnag) hooked into the error
                // handler stack that warning could be reported as a
                // real error. `xdebug_info('mode')` is silent and
                // returns the active modes as a list, so a presence
                // check is enough.
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

        // Walk the parent-class chain and link each ancestor's defining
        // file as a source dependency of this test. Captures the common
        // `tests/TestCase.php` case (where the user's base may be
        // trait-only and have no executable lines for the coverage
        // driver to pick up), and any deeper hierarchy. Vendor parents
        // are skipped — those are pinned by `composer.lock` and don't
        // need per-test edges. Same idea applies to traits used by the
        // ancestors: a trait's body executes when the test method
        // calls into it, so coverage already captures it; we only need
        // the explicit walk for ancestors whose own bodies might be
        // empty.
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
            $data = \pcov\collect(\pcov\inclusive);
        } else {
            /** @var array<string, mixed> $data */
            $data = \xdebug_get_code_coverage();
            // `true` resets Xdebug's internal buffer so the next `start()`
            // does not accumulate earlier tests' coverage into the current
            // one — otherwise the graph becomes progressively polluted.
            \xdebug_stop_code_coverage(true);
        }

        foreach (array_keys($data) as $sourceFile) {
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

        // Walk each covered class's interfaces / traits / parent chain
        // and link those files explicitly. Interface declarations have
        // no executable bytecode, so coverage drivers never emit lines
        // for them — without this walk, a signature change to an
        // interface like `Viewable` would leave the cached results of
        // every test that exercises an implementing class stale,
        // because the interface file never enters the graph through
        // the coverage path.
        $this->linkSourceDependencies(array_keys($data));

        $this->currentTestFile = null;
        $this->includedFilesAtTestStart = [];
    }

    /**
     * Records an extra source-file dependency for the currently-running
     * test. Used by collaborators that capture edges the coverage driver
     * cannot see — Blade templates rendered through Laravel's view
     * factory are the motivating case (their `.blade.php` source never
     * executes directly; a cached compiled PHP file does). No-op when
     * the recorder is inactive or no test is in flight, so callers can
     * fire it unconditionally from app-level hooks.
     */
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

    /**
     * Records source dependencies for a specific test file. Used for edges
     * captured before `Prepared` has opened the normal per-test recorder window.
     *
     * @param  iterable<int, string>  $sourceFiles
     */
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

    /**
     * For each project-local source file the coverage driver
     * captured for this test, finds the classes / interfaces / traits
     * declared in it and links every file in their declarative
     * hierarchy: implemented interfaces (transitive), used traits,
     * and parent classes (with their own interfaces and traits).
     *
     * Coverage drivers only record executable lines, so an interface
     * signature change (e.g. adding a return type to a `Viewable`
     * method) never registers — the interface file has no bytecode
     * to instrument. Without this walk, every class implementing the
     * interface would silently keep its stale cached result through
     * the change, even though `--parallel` (no TIA) catches the
     * incompatibility immediately.
     *
     * @param  array<int, string>  $coveredFiles  absolute paths from coverage
     */
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

    /**
     * Incrementally folds every project-local class / interface /
     * trait declared since the last refresh into `$fileToClassNames`.
     * PHP only ever appends to its declared-symbol lists (classes
     * never get unloaded), so iterating from `$indexedClassNames`'s
     * cardinality forward is sufficient — and over a long suite this
     * is dominated by the first test, since most classes are loaded
     * by then.
     */
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

            // Names came directly from `get_declared_*`, so the
            // class/interface/trait is guaranteed loaded — but
            // `class_exists($name, false)` (no autoload) keeps the
            // string narrowed to `class-string` for static analysis
            // and the `ReflectionClass` constructor stays in its
            // documented happy path.
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

    /**
     * Returns the project-local files the named class declaratively
     * depends on: implemented interfaces (transitive), used traits,
     * and the entire parent chain (each with their own interfaces
     * and traits). Cached per class because the answer is invariant
     * across a single process.
     *
     * @return list<string>
     */
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

        // `getInterfaceNames()` is transitive — it returns interfaces
        // from parent classes and parent interfaces too — so a single
        // pass covers the whole interface graph.
        foreach ($reflection->getInterfaceNames() as $iname) {
            $linkSymbol($iname);
        }

        // Direct + ancestor traits. `getTraitNames()` doesn't recurse
        // into traits-using-traits, but that's a rare pattern in
        // application code; if a project genuinely needs it, the
        // coverage driver will pick up the executed bytecode of the
        // outer trait and the dependency walk runs against the
        // resulting class anyway.
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

    /**
     * Records every project-local ancestor class's defining file as a
     * source dependency of the currently-running test. PCOV / Xdebug
     * record *executable lines* — a base class whose body is just
     * `class TestCase extends BaseTestCase { use CreatesApplication; }`
     * has no executable bytecode of its own, so the driver doesn't
     * emit a line for it and it never enters the graph through the
     * usual coverage path. This walk fills that gap by asking
     * reflection for each parent's file and linking it explicitly.
     */
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

    /**
     * Links project-local classes imported by the test file. This catches
     * declaration-only support classes / enums / interfaces that may never emit
     * executable coverage lines, and avoids relying on global autoload timing.
     */
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

    /**
     * True when `$className` (or any of its ancestors) uses one of
     * Laravel's database-resetting traits. Walking up `getTraits()` is
     * necessary because Pest test classes are eval'd from the
     * generated `*.php` test file and the trait usually lives on a
     * shared `tests/TestCase.php` ancestor. Result is cached per class
     * — class hierarchies don't change within a process.
     */
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

    /**
     * Records that the currently-running test queried `$table`. Called
     * by `TableTracker` for every DML statement Laravel's `DB::listen`
     * reports; the table name has already been extracted by
     * `TableExtractor::fromSql()` so we just store it. No-op outside
     * a test window, so the callback is safe to leave armed across
     * setUp / tearDown boundaries.
     */
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

    /**
     * Records that the currently-running test server-side-rendered the
     * named Inertia component. The name is whatever
     * `Inertia::render($component, …)` was called with — typically a
     * slash-separated path like `Users/Show` that maps to
     * `resources/js/Pages/Users/Show.vue`. No-op outside a test window
     * so the underlying listener can stay armed without leaking
     * state between tests.
     */
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

    /**
     * @return array<string, array<int, string>> absolute test file → list of absolute source files.
     */
    public function perTestFiles(): array
    {
        $out = [];

        foreach ($this->perTestFiles as $testFile => $sources) {
            $out[$testFile] = array_keys($sources);
        }

        return $out;
    }

    /**
     * @return array<string, array<int, string>> absolute test file → sorted list of table names.
     */
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

    /**
     * @return array<string, array<int, string>> absolute test file → sorted list of Inertia component names.
     */
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

    /**
     * @return array<string, true> absolute test file → true for tests using a Laravel DB-resetting trait.
     */
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

    /**
     * Resolves the file that *defines* the test class.
     *
     * Order of preference:
     *   1. Pest's generated `$__filename` static — the original `*.php` file
     *      containing the `test()` calls (the eval'd class itself has no file).
     *   2. `ReflectionClass::getFileName()` — the concrete class's file. This
     *      is intentionally more specific than `ReflectionMethod::getFileName()`
     *      (which would return the *trait* file for methods brought in via
     *      `uses SharedTestBehavior`).
     */
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
     * Clears all captured state. Useful for long-running hosts (daemons,
     * PHP-FPM, watchers) that invoke Pest multiple times in a single process
     * — without this, coverage from run N would bleed into run N+1.
     */
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
