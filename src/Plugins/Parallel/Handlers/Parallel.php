<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Handlers;

use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel\Paratest\WrapperRunner;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
final class Parallel
{
    use HandleArguments;
    /**
     * @var string[]
     */
    private const ARGS_TO_REMOVE = [
        '--parallel',
        '-p',
        '--no-output',
    ];

    public function handle(array $args): array
    {
        $args = array_reduce(self::ARGS_TO_REMOVE, fn ($args, $arg): array => $this->popArgument($arg, $args), $args);

        return $this->pushArgument('--runner=' . WrapperRunner::class, $args);
    }
}
