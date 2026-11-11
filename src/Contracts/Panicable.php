<?php

declare(strict_types=1);

namespace Pest\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
interface Panicable
{
    public function render(OutputInterface $output): void;

    public function exitCode(): int;
}
