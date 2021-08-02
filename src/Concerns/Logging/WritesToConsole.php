<?php

declare(strict_types=1);

namespace Pest\Concerns\Logging;

/**
 * @internal
 */
trait WritesToConsole
{
    private function writeSuccess(string $message): void
    {
        $this->writePestTestOutput($message, 'fg-green, bold', '✓');
    }

    private function writeError(string $message): void
    {
        $this->writePestTestOutput($message, 'fg-red, bold', '⨯');
    }

    private function writeWarning(string $message): void
    {
        $this->writePestTestOutput($message, 'fg-yellow, bold', '-');
    }

    private function writePestTestOutput(string $message, string $color, string $symbol): void
    {
        $this->writeWithColor($color, "$symbol ", false);
        $this->write($message);
        $this->writeNewLine();
    }
}
