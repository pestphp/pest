<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Pest\Contracts\Panicable;
use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class BaselineFetchFailed extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct(
        private readonly string $headline,
        private readonly string $hint,
    ) {
        parent::__construct($headline);
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> TIA </> '.$this->headline,
            '  <fg=gray>'.$this->hint.'</>',
            '  <fg=gray>Bypass with</> <fg=cyan>--fresh</> <fg=gray>to record locally and skip the baseline fetch.</>',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
