<?php

declare(strict_types=1);

namespace Pest\Concerns\Logging;

/**
 * @internal
 */
trait WritesToConsole
{
    /**
     * Writes the given success message to the console.
     */
    private function writeSuccess(string $message): void
    {
        $this->writePestTestOutput($message, 'fg-green, bold', '✓');
    }

    /**
     * Writes the given error message to the console.
     */
    private function writeError(string $message): void
    {
        $this->writePestTestOutput($message, 'fg-red, bold', '⨯');
    }

    /**
     * Writes the given warning message to the console.
     */
    private function writeWarning(string $message): void
    {
        $this->writePestTestOutput($message, 'fg-yellow, bold', '-');
    }

    /**
     * Writes the give message to the console.
     */
    private function writePestTestOutput(string $message, string $color, string $symbol): void
    {
        $this->writeWithColor($color, "$symbol ", false);
        $this->write($message);
        $this->writeNewLine();
    }
}
