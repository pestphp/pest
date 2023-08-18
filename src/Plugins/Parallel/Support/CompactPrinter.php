<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Support;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\Style;
use ParaTest\Options;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;
use SebastianBergmann\Timer\Duration;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Termwind\Terminal;

use function Termwind\render;
use function Termwind\terminal;

/**
 * @internal
 */
final class CompactPrinter
{
    /**
     * The number of processed tests.
     */
    private int $processed = 0;

    /**
     * @var array<string, array<int, string>>
     */
    private const LOOKUP_TABLE = [
        '.' => ['gray', '.'],
        'S' => ['yellow', 's'],
        'T' => ['cyan', 't'],
        'I' => ['yellow', '!'],
        'N' => ['yellow', '!'],
        'D' => ['yellow', '!'],
        'R' => ['yellow', '!'],
        'W' => ['yellow', '!'],
        'E' => ['red', '⨯'],
        'F' => ['red', '⨯'],
    ];

    /**
     * Creates a new instance of the Compact Printer.
     */
    public function __construct(
        private readonly Terminal $terminal,
        private readonly OutputInterface $output,
        private readonly Style $style,
        private readonly int $compactSymbolsPerLine,
    ) {
        // ..
    }

    /**
     * Creates a new instance of the Compact Printer.
     */
    public static function default(): self
    {
        return new self(
            terminal(),
            new ConsoleOutput(decorated: true),
            new Style(new ConsoleOutput(decorated: true)),
            terminal()->width() - 4,
        );
    }

    /**
     * Output an empty line in the console. Useful for providing a little breathing room.
     */
    public function newLine(): void
    {
        render('<div class="py-1"></div>');
    }

    /**
     * Outputs the given description item from the ProgressPrinter as a gorgeous, colored symbol.
     */
    public function descriptionItem(string $item): void
    {
        [$color, $icon] = self::LOOKUP_TABLE[$item] ?? self::LOOKUP_TABLE['.'];

        $symbolsOnCurrentLine = $this->processed % $this->compactSymbolsPerLine;

        if ($symbolsOnCurrentLine >= $this->terminal->width() - 4) {
            $symbolsOnCurrentLine = 0;
        }

        if ($symbolsOnCurrentLine === 0) {
            $this->output->writeln('');
            $this->output->write('  ');
        }

        $this->output->write(sprintf('<fg=%s;options=bold>%s</>', $color, $icon));

        $this->processed++;
    }

    /**
     * Outputs all errors from the given state using Collision's beautiful error output.
     */
    public function errors(State $state): void
    {
        $this->output->writeln('');

        $this->style->writeErrorsSummary($state);
    }

    /**
     * Outputs a clean recap of the test run, including the number of tests, assertions, and failures.
     */
    public function recap(State $state, PHPUnitTestResult $testResult, Duration $duration, Options $options): void
    {
        assert($this->output instanceof ConsoleOutput);

        $nanoseconds = $duration->asNanoseconds() % 1_000_000_000;
        $snapshotDuration = HRTime::fromSecondsAndNanoseconds((int) $duration->asSeconds(), $nanoseconds);
        $telemetryDuration = \PHPUnit\Event\Telemetry\Duration::fromSecondsAndNanoseconds((int) $duration->asSeconds(), $nanoseconds);

        $status = gc_status();

        $garbageCollectorStatus = new GarbageCollectorStatus(
            $status['runs'],
            $status['collected'],
            $status['threshold'],
            $status['roots'],
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );

        $telemetry = new Info(
            new Snapshot(
                $snapshotDuration,
                MemoryUsage::fromBytes(0),
                MemoryUsage::fromBytes(0),
                $garbageCollectorStatus,
            ),
            $telemetryDuration,
            MemoryUsage::fromBytes(0),
            \PHPUnit\Event\Telemetry\Duration::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
        );

        $this->style->writeRecap($state, $telemetry, $testResult);

        $this->output->write("\033[1A");

        $this->output->write([
            sprintf(
                '  <fg=gray>Parallel:</> <fg=default>%s process%s</>',
                $options->processes,
                $options->processes > 1 ? 'es' : '',
            ),
            "\n",
            "\n",
        ]);
    }
}
