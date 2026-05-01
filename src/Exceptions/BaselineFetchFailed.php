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
    ) {
        parent::__construct($headline);
    }

    public function render(OutputInterface $output): void
    {
        View::renderUsing($output);

        View::render('components.badge', ['type' => 'ERROR', 'content' => $this->headline]);
        View::render('components.two-column-detail', ['left' => $this->hint, 'right' => '']);
        View::render('components.two-column-detail', [
            'left' => 'Bypass with --fresh to record locally and skip the baseline fetch.',
            'right' => '',
        ]);
    }

    public function exitCode(): int
    {
        return 1;
    }
}
