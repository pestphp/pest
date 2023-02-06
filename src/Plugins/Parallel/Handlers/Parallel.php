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

    public function handle(array $args): array
    {
        $argsToRemove = [
            '--parallel',
            '-p',
            '--no-output',
        ];

        $args = array_reduce($argsToRemove, fn ($args, $arg) => $this->popArgument($arg, $args), $args);

        return $this->pushArgument('--runner=' . WrapperRunner::class, $args);
    }
}
