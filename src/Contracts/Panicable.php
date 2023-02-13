<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
interface Panicable
{
    /**
     * Renders the panic on the given output.
     */
    public function render(OutputInterface $output): void;

    /**
     * The exit code to be used.
     */
    public function exitCode(): int;
}
