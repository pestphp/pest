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
final class TiaRequiresPestTests extends RuntimeException implements ExceptionInterface, Panicable, RenderlessEditor, RenderlessTrace
{
    public function __construct(private readonly string $className, string $filename)
    {
        parent::__construct(sprintf(
            'Tia mode requires only functional based Pest tests, but encountered PHPUnit class [%s] in [%s].',
            $className,
            $filename,
        ));
    }

    public function render(OutputInterface $output): void
    {
        $output->writeln([
            '',
            '  <fg=white;options=bold;bg=red> ERROR </> Tia mode requires Pest tests.',
            '',
            sprintf('  Encountered PHPUnit class <fg=yellow>%s</>', $this->className),
            sprintf('  in <fg=gray>%s</>.', $this->file),
            '',
            '  Convert it to a Pest test, or run without Tia.',
            '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
