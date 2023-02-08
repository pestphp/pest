<?php

namespace Pest\Plugins\Parallel\Paratest;

use Symfony\Component\Console\Output\ConsoleOutput;

class CleanConsoleOutput extends ConsoleOutput
{
    protected function doWrite(string $message, bool $newline): void
    {
        if ($this->isOpeningHeadline($message)) {
            return;
        }

        parent::doWrite($message, $newline);
    }

    private function isOpeningHeadline(string $message): bool
    {
        return str_contains($message, 'by Sebastian Bergmann and contributors.');
    }
}
