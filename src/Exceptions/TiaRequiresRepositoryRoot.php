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
final class TiaRequiresRepositoryRoot extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct(private readonly string $subdirectoryPrefix)
    {
        parent::__construct(sprintf(
            'Tia mode requires the project root to be the git repository root, but this project sits in the subdirectory [%s] of a larger repository. Please give it its own repository to use Tia.',
            $this->subdirectoryPrefix,
        ));
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> ERROR </> Tia mode requires the git repository root.',
            '',
            sprintf('  This project sits in a subdirectory of a larger repo <fg=yellow>%s</>.', $this->subdirectoryPrefix),
            '',
            '  Give the project its own git repository to use Tia.',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
