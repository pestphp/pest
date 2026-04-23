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
     * Cached class → test file resolution.
     *
     * @var array<string, string|null>
     */
    private array $classFileCache = [];

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
            } elseif (function_exists('xdebug_start_code_coverage')) {
                // Xdebug is loaded. Probe whether coverage mode is active by
                // attempting a start — it emits E_WARNING when the mode is off.
                // We capture the warning via a temporary error handler.
                $probeOk = true;
                set_error_handler(static function () use (&$probeOk): bool {
                    $probeOk = false;

                    return true;
                });
                \xdebug_start_code_coverage();
                restore_error_handler();

                if ($probeOk) {
                    \xdebug_stop_code_coverage(false);
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
        $this->classFileCache = [];
        $this->active = false;
    }
}
