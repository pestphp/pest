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
final class TiaRequiresDefaultBranch extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct()
    {
        parent::__construct(
            'Tia mode could not determine the default branch every other branch falls back to reading.',
        );
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> ERROR </> Tia mode could not determine the default branch.',
            '',
            '  It is the branch whose baseline every other branch falls back to reading, and',
            '  nothing in this checkout names it: the repository has a remote, but no',
            '  <fg=yellow>origin/HEAD</>, and no CI provider stated it either. Guessing would re-run the',
            '  whole suite on every new branch while reporting it as a cache hit.',
            '',
            '  Name the branch in <fg=yellow>tests/Pest.php</>:',
            '',
            '    <fg=yellow>pest()->tia()->defaultBranch(\'master\');</>',
            '',
            '  Or let git answer, once per clone:',
            '',
            '    <fg=yellow>git remote set-head origin --auto</>',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
