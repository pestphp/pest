<?php

declare(strict_types=1);

namespace Pest\Restarters;

use Pest\Contracts\Restarter;
use Pest\Plugins\Tia;

/**
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
            putenv(self::ENV_RESTARTED);
            unset($_ENV[self::ENV_RESTARTED]);

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
            $env[$name] = $value;
        }

        return $env;
    }

    private function normalise(string $path): string
    {
        return rtrim($path, '/\\');
    }
}
