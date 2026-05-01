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
 * Raised when fetching the team-shared TIA baseline hits an error
 * that's actionable rather than transient — missing `gh`, broken
 * auth, scope/perms misconfiguration, or a CI publish that produced
 * an unreadable artifact. Silently falling through to a full record
 * would paper over the bug and waste minutes; better to stop, tell
 * the user what to fix, and offer the `--fresh` escape hatch.
 *
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
