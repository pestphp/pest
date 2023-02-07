<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Support;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\Style;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use NunoMaduro\Collision\Exceptions\ShouldNotHappen;
use Pest\Logging\TeamCity\Converter;
use Pest\Support\StateGenerator;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Event;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\IncompleteTestError;
use PHPUnit\Metadata\MetadataCollection;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;
use ReflectionClass;
use SebastianBergmann\Timer\Duration;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Termwind\Terminal;
use function Termwind\render;
use function Termwind\renderUsing;
use function Termwind\terminal;

final class CompactPrinter
{
    private readonly Terminal $terminal;
    private readonly ConsoleOutputInterface $output;
    private readonly Style $style;

    private int $compactProcessed = 0;
    private int $compactSymbolsPerLine = 0;

    public function __construct()
    {
        $this->terminal = terminal();
        $this->output = new ConsoleOutput(decorated: true);
        $this->style = new Style($this->output);

        $this->compactSymbolsPerLine = $this->terminal->width() - 4;
    }

    public function newLine(): void
    {
        render('<div class="py-1"></div>');
    }

    public function line(string $message): void
    {
        render("<span class='mx-2 py-1 text-gray'>{$message}</span>");
    }

    public function descriptionItem(string $item): void
    {
        // TODO: Support TODOs

        $lookupTable = [
            '.' => ['gray', '.'],
            'S' => ['yellow', 's'],
            'I' => ['yellow', 'i'],
            'N' => ['yellow', 'i'],
            'R' => ['yellow', '!'],
            'W' => ['yellow', '!'],
            'E' => ['red', '⨯'],
            'F' => ['red', '⨯'],
        ];

        [$color, $icon] = $lookupTable[$item] ?? $lookupTable['.'];

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

    public function errors(State $state): void
    {
        $this->style->writeErrorsSummary($state, false);
    }

    public function recap(State $state, PHPUnitTestResult $testResult, Duration $duration): void
    {
        assert($this->output instanceof ConsoleOutput);
        $style = new Style($this->output);

        $nanoseconds = $duration->asNanoseconds() % 1000000000;
        $snapshotDuration = HRTime::fromSecondsAndNanoseconds((int)$duration->asSeconds(), $nanoseconds);
        $telemetryDuration = \PHPUnit\Event\Telemetry\Duration::fromSecondsAndNanoseconds((int)$duration->asSeconds(), $nanoseconds);

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

        $style->writeRecap($state, $telemetry, $testResult);
    }
}
