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
final class TiaBaselineCommitMissing extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct(private readonly string $sha)
    {
        parent::__construct(sprintf(
            'The Tia baseline was recorded at commit %s, which cannot be reached from this branch, so the files changed since then cannot be listed.',
            $sha,
        ));
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            sprintf('  <fg=white;options=bold;bg=red> ERROR </> The Tia baseline commit %s cannot be reached from this branch.', substr($this->sha, 0, 12)),
            '',
            '  Either the commit is not in this clone, or this branch does not contain it.',
            '  Without it the files changed since the baseline cannot be listed, and every',
            '  test would be replayed as unchanged.',
            '',
            '  Fetch it, or merge the branch it was recorded on, then run again:',
            '',
            '    <fg=yellow>git fetch</>',
            '',
            '  Or use <fg=yellow>--fresh</> to record locally.',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
