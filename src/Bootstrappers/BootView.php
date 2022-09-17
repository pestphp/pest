<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Support\View;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class BootView
{
    public function __construct(
        private readonly OutputInterface $output
    ) {
        // ..
    }

    /**
     * Boots the view renderer.
     */
    public function __invoke(): void
    {
        View::renderUsing($this->output);
    }
}
