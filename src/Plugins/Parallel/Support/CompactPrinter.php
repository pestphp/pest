<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Support;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\Style;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;
use SebastianBergmann\Timer\Duration;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use function Termwind\render;
use Termwind\Terminal;
use function Termwind\terminal;

/**
 * @internal
 */
final class CompactPrinter
{
    private readonly Terminal $terminal;

    private readonly ConsoleOutputInterface $output;

    private readonly Style $style;

    private int $compactProcessed = 0;

    private readonly int $compactSymbolsPerLine;

    /**
     * @var array<string, array<int, string>>
     */
    private const LOOKUP_TABLE = [
        '.' => ['gray', '.'],
        'S' => ['yellow', 's'],
        'I' => ['yellow', 'i'],
        'N' => ['yellow', 'i'],
        'R' => ['yellow', '!'],
        'W' => ['yellow', '!'],
        'E' => ['red', '⨯'],
        'F' => ['red', '⨯'],
    ];

    public function __construct()
    {
        $this->terminal = terminal();
        $this->output = new ConsoleOutput(decorated: true);
        $this->style = new Style($this->output);

        $this->compactSymbolsPerLine = $this->terminal->width() - 4;
    }

    /**
     * Output an empty line in the console. Useful for providing a little breathing room.
     */
    public function newLine(): void
    {
        render('<div class="py-1"></div>');
    }

    /**
     * Write the given message to the console, adding vertical and horizontal padding.
     */
    public function line(string $message): void
    {
        render("<span class='mx-2 py-1 text-gray-700'>{$message}</span>");
    }

    /**
     * Outputs the given description item from the ProgressPrinter as a gorgeous, colored symbol.
     */
    public function descriptionItem(string $item): void
    {
        [$color, $icon] = self::LOOKUP_TABLE[$item] ?? self::LOOKUP_TABLE['.'];

        $symbolsOnCurrentLine = $this->compactProcessed % $this->compactSymbolsPerLine;

        if ($symbolsOnCurrentLine >= $this->terminal->width() - 4) {
            $symbolsOnCurrentLine = 0;
        }

        if ($symbolsOnCurrentLine === 0) {
            $this->output->writeln('');
            $this->output->write('  ');
        }

        $this->output->write(sprintf('<fg=%s;options=bold>%s</>', $color, $icon));

        $this->compactProcessed++;
    }

    /**
     * Outputs all errors from the given state using Collision's beautiful error output.
     */
    public function errors(State $state): void
    {
        $this->style->writeErrorsSummary($state, false);
    }

    /**
     * Outputs a clean recap of the test run, including the number of tests, assertions, and failures.
     */
    public function recap(State $state, PHPUnitTestResult $testResult, Duration $duration): void
    {
        assert($this->output instanceof ConsoleOutput);

        $nanoseconds = $duration->asNanoseconds() % 1_000_000_000;
        $snapshotDuration = HRTime::fromSecondsAndNanoseconds((int) $duration->asSeconds(), $nanoseconds);
        $telemetryDuration = \PHPUnit\Event\Telemetry\Duration::fromSecondsAndNanoseconds((int) $duration->asSeconds(), $nanoseconds);

        $telemetry = new Info(
            new Snapshot(
                $snapshotDuration,
                MemoryUsage::fromBytes(0),
                MemoryUsage::fromBytes(0),
            ),
            $telemetryDuration,
            MemoryUsage::fromBytes(0),
            \PHPUnit\Event\Telemetry\Duration::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
        );

        $this->style->writeRecap($state, $telemetry, $testResult);
    }
}
