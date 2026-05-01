<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * Re-execs the PHP process with `pcov.directory` pinned to the project
 * root so pcov never instruments anything outside it (vendor, system
 * includes, etc.).
 *
 * pcov reads `pcov.directory` once, on the first file it instruments —
 * setting it via `ini_set()` from inside the test runner is too late
 * for files already compiled by Composer's autoloader. Restarting the
 * process with `-dpcov.directory=<root>` from the very top of `bin/pest`
 * means *every* file pcov sees is filtered correctly.
 *
 * Only fires when ALL of these hold:
 *   1. The pcov extension is loaded.
 *   2. `--tia` is present in argv (plain `pest` runs are unaffected).
 *   3. The current `pcov.directory` differs from the project root.
 *   4. We are not already the restarted process — guarded by an env
 *      sentinel so a single round-trip is enough.
 *
 * Modelled after {@see XdebugGuard}: the same "check before doing real
 * work in `bin/pest`" position, the same conservative gating around
 * `--tia`. They are independent — both can fire on the same invocation
 * (the user has pcov *and* xdebug loaded), in which case Xdebug is
 * dropped first and the pcov restart inherits the slimmer process.
 *
 * @internal
 */
final class PcovGuard
{
    private const string ENV_RESTARTED = 'PEST_PCOV_GUARD_RESTARTED';

    /**
     * Call as early as possible after Composer autoload, before any
     * Pest class beyond the autoloader is touched. Idempotent and
     * defensive — returns silently when pcov isn't installed, when the
     * INI is already correct, or when we've already restarted.
     */
    public static function maybeRestart(string $projectRoot): void
    {
        if (! extension_loaded('pcov')) {
            return;
        }

        if (getenv(self::ENV_RESTARTED) === '1') {
            return;
        }

        $argv = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];

        if (! self::hasTiaFlag($argv)) {
            return;
        }

        $desired = self::normalise($projectRoot);
        $current = self::normalise((string) ini_get('pcov.directory'));

        if ($current === $desired) {
            return;
        }

        self::restart($projectRoot, $argv);
    }

    /**
     * @param  array<int, mixed>  $argv
     */
    private static function hasTiaFlag(array $argv): bool
    {
        foreach ($argv as $value) {
            if (is_string($value) && $value === '--tia') {
                return true;
            }
        }

        return false;
    }

    /**
     * Spawns a child PHP process inheriting our stdin/stdout/stderr and
     * exits with its status. `pcntl_exec` would be the cleanest path
     * (replaces the current process, no double-buffering) but it isn't
     * available on Windows or in environments that disable it; the
     * `proc_open` fallback works everywhere PHP runs.
     *
     * @param  array<int, mixed>  $argv
     */
    private static function restart(string $projectRoot, array $argv): void
    {
        $script = self::scriptArgv($argv);

        if ($script === null) {
            return;
        }

        $env = self::inheritEnv();
        $env[self::ENV_RESTARTED] = '1';

        $command = array_merge(
            [PHP_BINARY, '-d', 'pcov.directory='.$projectRoot],
            $script,
        );

        if (function_exists('pcntl_exec')) {
            // `pcntl_exec` returns false on failure and replaces the
            // process on success — no `exit` needed in the success path.
            // Pass the env explicitly because pcntl_exec doesn't inherit
            // by default.
            $binary = array_shift($command);

            if (is_string($binary)) {
                @pcntl_exec($binary, $command, $env);
            }

            // If we're still here, pcntl_exec failed; fall through.
        }

        $proc = @proc_open(
            $command,
            [STDIN, STDOUT, STDERR],
            $pipes,
            null,
            $env,
        );

        if (! is_resource($proc)) {
            return;
        }

        $exitCode = proc_close($proc);

        exit($exitCode === -1 ? 1 : $exitCode);
    }

    /**
     * Reconstructs the argv we want the child process to receive: the
     * script path followed by every original argument. Returns null
     * when argv is malformed and we can't safely restart.
     *
     * @param  array<int, mixed>  $argv
     * @return list<string>|null
     */
    private static function scriptArgv(array $argv): ?array
    {
        $out = [];

        foreach ($argv as $value) {
            if (! is_string($value)) {
                return null;
            }

            $out[] = $value;
        }

        return $out === [] ? null : $out;
    }

    /**
     * @return array<string, string>
     */
    private static function inheritEnv(): array
    {
        $env = [];

        foreach (getenv() as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $env[$name] = $value;
            }
        }

        return $env;
    }

    private static function normalise(string $path): string
    {
        return rtrim($path, '/\\');
    }
}
