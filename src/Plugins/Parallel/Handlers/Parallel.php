<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;

/**
 * @internal
 */
final class Parallel implements HandlesArguments
{
    use HandleArguments;

    /**
     * The list of arguments to remove.
     */
    private const ARGS_TO_REMOVE = [
        '--parallel',
        '-p',
        '--no-output',
        '--cache-result',
    ];

    /**
     * Handles the arguments, removing the ones that are not needed, and adds the "runner" argument.
     */
    public function handleArguments(array $arguments): array
    {
        $args = array_reduce(self::ARGS_TO_REMOVE, fn (array $args, string $arg): array => $this->popArgument($arg, $args), $arguments);

        return $this->pushArgument('--runner='.WrapperRunner::class, $args);
    }
}
