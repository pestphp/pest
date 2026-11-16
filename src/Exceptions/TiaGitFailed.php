<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Pest\Contracts\Panicable;
use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class TiaGitFailed extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct(private readonly string $error)
    {
        parent::__construct(sprintf('Tia mode could not read the git repository: %s', $this->error));
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> ERROR </> Tia mode could not read the git repository.',
            '',
            '  Git is installed, but it failed with:',
            '',
        ]);

        foreach (explode("\n", $this->error) as $line) {
            $output->writeln('    <fg=yellow>'.OutputFormatter::escape(rtrim($line)).'</>');
        }

        $output->writeln([
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
