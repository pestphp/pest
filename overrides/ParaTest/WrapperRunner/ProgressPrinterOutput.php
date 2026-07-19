<?php

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2011 Brian Scaturro
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use PHPUnit\TextUI\Output\Printer;

use function preg_match;

/**
 * @internal
 *
 * This file is overridden so the "T" progress character — emitted by Pest for
 * "todo" tests — is routed to the progress file next to the regular characters,
 * instead of being treated as unexpected output.
 */
final readonly class ProgressPrinterOutput implements Printer
{
    public function __construct(
        private Printer $progressPrinter,
        private Printer $outputPrinter,
    ) {}

    public function print(string $buffer): void
    {
        // Skip anything in \PHPUnit\TextUI\Output\Default\ProgressPrinter\ProgressPrinter::printProgress except $progress
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
