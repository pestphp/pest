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
final class NoDirtyTestsFound extends InvalidArgumentException implements ExceptionInterface, RenderlessEditor, RenderlessTrace, Panicable
{
    /**
     * Renders the panic on the given output.
     */
    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=blue> INFO </> No "dirty" tests found.',
            '',
        ]);
    }

    /**
     * The exit code to be used.
     */
    public function exitCode(): int
    {
        return 0;
    }
}
