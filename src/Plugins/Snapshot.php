<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\TestSuite;

/**
 * @internal
 */
final class Snapshot implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * Whether snapshots should be updated on this run.
     */
    public static bool $updateSnapshots = false;

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (Parallel::isWorker() && Parallel::getGlobal('UPDATE_SNAPSHOTS') === true) {
            self::$updateSnapshots = true;

            return $arguments;
        }

        if (! $this->hasArgument('--update-snapshots', $arguments)) {
            return $arguments;
        }

        self::$updateSnapshots = true;

        if ($this->isFullRun($arguments)) {
            TestSuite::getInstance()->snapshots->flush();
        }

        if ($this->hasArgument('--parallel', $arguments) || $this->hasArgument('-p', $arguments)) {
            Parallel::setGlobal('UPDATE_SNAPSHOTS', true);
        }

        return $this->popArgument('--update-snapshots', $arguments);
    }

    /**
     * Options that take a value as the next argument (rather than via "=value").
     *
     * @var list<string>
     */
    private const array FLAGS_WITH_VALUES = [
        '--filter',
        '--group',
        '--exclude-group',
        '--test-suffix',
        '--covers',
        '--uses',
        '--cache-directory',
        '--cache-result-file',
        '--configuration',
        '--colors',
        '--test-directory',
        '--bootstrap',
        '--order-by',
        '--random-order-seed',
        '--log-junit',
        '--log-teamcity',
        '--log-events-text',
        '--log-events-verbose-text',
        '--coverage-clover',
        '--coverage-cobertura',
        '--coverage-crap4j',
        '--coverage-html',
        '--coverage-php',
        '--coverage-text',
        '--coverage-xml',
        '--assignee',
        '--issue',
        '--ticket',
        '--pr',
        '--pull-request',
        '--retry',
        '--shard',
        '--repeat',
    ];

    /**
     * Determines whether the command targets the entire suite (no filter, no path).
     *
     * @param  array<int, string>  $arguments
     */
    private function isFullRun(array $arguments): bool
    {
        if ($this->hasArgument('--filter', $arguments)) {
            return false;
        }

        $tokens = array_slice($arguments, 1);
        $skipNext = false;

        foreach ($tokens as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if ($arg === '') {
                continue;
            }

            if ($arg[0] === '-') {
                if (in_array($arg, self::FLAGS_WITH_VALUES, true)) {
                    $skipNext = true;
                }

                continue;
            }

            return false;
        }

        return true;
    }
}
