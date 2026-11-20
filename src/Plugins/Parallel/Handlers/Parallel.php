<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel as ParallelPlugin;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;

/**
 * @internal
 */
final class Parallel implements HandlesArguments
{
    use HandleArguments;

    private const array ARGS_TO_REMOVE = [
        '--parallel',
        '-p',
        '--no-output',
        '--no-results',
        '--cache-result',
        '--do-not-cache-result',
        '--record-test-run-history',
        '--do-not-record-test-run-history',
    ];

    private const array ARGS_THAT_DISABLE_TEST_RUN_HISTORY = [
        '--do-not-cache-result',
        '--do-not-record-test-run-history',
    ];

    public function handleArguments(array $arguments): array
    {
        if (array_any(self::ARGS_THAT_DISABLE_TEST_RUN_HISTORY, fn (string $argument): bool => $this->hasArgument($argument, $arguments))) {
            ParallelPlugin::setGlobal(WrapperRunner::DO_NOT_RECORD_TEST_RUN_HISTORY, true);
        }

        $args = array_reduce(self::ARGS_TO_REMOVE, fn (array $args, string $arg): array => $this->popArgument($arg, $args), $arguments);

        return $this->pushArgument('--runner='.WrapperRunner::class, $args);
    }
}
