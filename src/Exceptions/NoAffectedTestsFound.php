<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use InvalidArgumentException;
use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Pest\Contracts\Panicable;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class NoAffectedTestsFound extends InvalidArgumentException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=blue> INFO </> No affected tests found.',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 0;
    }
}
