<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use PHPUnit\TextUI\Output\Printer;

use function preg_match;

/**
 * @internal
 */
final readonly class ProgressPrinterOutput implements Printer
{
    public function __construct(
        private Printer $progressPrinter,
        private Printer $outputPrinter,
    ) {}

    public function print(string $buffer): void
    {
        if (
            $buffer === "\n"
            || preg_match('/^ +$/', $buffer) === 1
            || preg_match('/^ \d+ \/ \d+ \(...%\)$/', $buffer) === 1
        ) {
            return;
        }

        match ($buffer) {
            'E', 'F', 'I', 'N', 'D', 'R', 'W', 'S', 'T', '.' => $this->progressPrinter->print($buffer),
            default => $this->outputPrinter->print($buffer),
        };
    }

    public function flush(): void
    {
        $this->progressPrinter->flush();
        $this->outputPrinter->flush();
    }
}
