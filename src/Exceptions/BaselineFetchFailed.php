<?php

declare(strict_types=1);

namespace Pest\Exceptions;

use NunoMaduro\Collision\Contracts\RenderlessEditor;
use NunoMaduro\Collision\Contracts\RenderlessTrace;
use Pest\Contracts\Panicable;
use Pest\Support\View;
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
        private readonly bool $hasAnchor = false,
    ) {
        parent::__construct($headline);
    }

    public function render(OutputInterface $output): void
    {
        View::renderUsing($output);

        if (! $this->hasAnchor) {
            View::render('components.badge', ['type' => 'ERROR', 'content' => $this->headline]);
            $this->renderChild($output, $this->hint.' Or use [--fresh] to record locally.');
            $output->writeln('');

            return;
        }

        $this->renderChild($output, $this->headline);
        $this->renderChild($output, $this->hint.' Or use [--fresh] to record locally.');
        $output->writeln('');
    }

    public function exitCode(): int
    {
        return 1;
    }

    private function renderChild(OutputInterface $output, string $text): void
    {
        $output->writeln(sprintf('  <fg=gray>─ %s</>', $text));
    }
}
