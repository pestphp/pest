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
final class TiaRequiresCommit extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct()
    {
        parent::__construct(
            'Tia mode requires a repository with at least one commit, so the baseline it records can be anchored to a revision.',
        );
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> ERROR </> Tia mode requires at least one commit.',
            '',
            '  A baseline is anchored to the revision it was recorded at, and this repository',
            '  has none yet, so there is nothing to record against and nothing to compare a',
            '  later run to.',
            '',
            '  Commit once, then run again:',
            '',
            '    <fg=yellow>git add . && git commit -m "Initial commit"</>',
            '',
            '  Runs without <fg=yellow>--tia</> are unaffected.',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
