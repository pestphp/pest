<?php

declare(strict_types=1);

namespace Pest\Restarters;

use Pest\Contracts\Restarter;
use Pest\Plugins\Tia;

/**
 * Re-execs the PHP process with `pcov.directory` pinned to the project
 * root so pcov never instruments anything outside it (vendor, system
 * includes, etc.).
 *
 * pcov reads `pcov.directory` once, on the first file it instruments —
 * setting it via `ini_set()` from inside the test runner is too late
 * for files already compiled by Composer's autoloader. Restarting with
 * `-dpcov.directory=<root>` means *every* file pcov sees is filtered
 * correctly.
 *
 * Only fires when ALL of these hold:
 *   1. The pcov extension is loaded.
 *   2. TIA is enabled for this run (see {@see Tia::isEnabledForRun()} —
 *      either `--tia` on the CLI or `pest()->tia()->always()`); plain
 *      `pest` runs are unaffected.
 *   3. The current `pcov.directory` differs from the project root.
 *   4. We are not already the restarted process — guarded by an env
 *      sentinel so a single round-trip is enough.
 *
 * @internal
 */
final class PcovRestarter implements Restarter
{
    private const string ENV_RESTARTED = 'PEST_PCOV_RESTARTER_RESTARTED';

    /**
     * @param  array<int, string>  $arguments
     */
    public function maybeRestart(string $projectRoot, array $arguments): void
    {
        if (! extension_loaded('pcov')) {
            return;
        }

        if (getenv(self::ENV_RESTARTED) === '1') {
            return;
        }

        if (! Tia::isEnabledForRun($arguments)) {
            return;
        }

        $desired = $this->normalise($projectRoot);
        $current = $this->normalise((string) ini_get('pcov.directory'));

        if ($current === $desired) {
            return;
        }

        $this->restart($projectRoot, $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function restart(string $projectRoot, array $arguments): void
    {
        $env = $this->inheritEnv();
        $env[self::ENV_RESTARTED] = '1';

        $command = array_merge(
            [PHP_BINARY, '-d', 'pcov.directory='.$projectRoot],
            array_values($arguments),
        );

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
     * @return array<string, string>
     */
    private function inheritEnv(): array
    {
        $env = [];

        foreach (getenv() as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $env[$name] = $value;
            }
        }

        return $env;
    }

    private function normalise(string $path): string
    {
        return rtrim($path, '/\\');
    }
}
