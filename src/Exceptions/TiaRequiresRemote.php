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
final class TiaRequiresRemote extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct()
    {
        parent::__construct(
            'Tia mode requires a repository with a remote, so the default branch every other branch falls back to reading can be resolved.',
        );
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> ERROR </> Tia mode requires a repository with a remote.',
            '',
            '  Without one there is no way to tell which branch is the default — the branch',
            '  whose baseline every other branch falls back to reading — so the first run on',
            '  each new branch would silently re-run the whole suite.',
            '',
            '  Add a remote, or name the branch yourself in <fg=yellow>tests/Pest.php</>:',
            '',
            '    <fg=yellow>pest()->tia()->defaultBranch(\'master\');</>',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
