<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

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

        $file = $this->resolveTestFile($className, $fallbackFile);

        if ($file === null) {
            return;
        }

        $this->currentTestFile = $file;

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

        $this->currentTestFile = null;
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
        $this->active = false;
    }
}
