<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Support;

use NunoMaduro\Collision\Adapters\Phpunit\State;
use NunoMaduro\Collision\Adapters\Phpunit\Style;
use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use NunoMaduro\Collision\Exceptions\ShouldNotHappen;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\IncompleteTestError;
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
        render("<span class='mx-2 py-1 text-gray-700'>{$message}</span>");
    }

    public function descriptionItem(string $item): void
    {
        // TODO: Support todos

        $icon = match (strtolower($item)) {
            'f', 'e' => '⨯', // FAILED
            's' => 's', // SKIPPED
            'w', 'r' => '!', // WARN, RISKY
            'i' => '…', // INCOMPLETE
            '.' => '.', // PASSED
            default => $item,
        };

        $color = match (strtolower($item)) {
            'f', 'e' => 'red',
            'd', 's', 'i', 'r', 'w' => 'yellow',
            default => 'gray',
        };

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

        //switch ($item) {
        //    case self::TODO:
        //        return '↓';
        //    case self::RUNS:
        //        return '•';
        //    default:
        //        return '✓';
        //}
    }

    public function errors(array $errors): void
    {
        array_map(function (TestResult $testResult): void {
            if (! $testResult->throwable instanceof \PHPUnit\Event\Code\Throwable) {
                throw new ShouldNotHappen();
            }

            renderUsing($this->output);
            render(<<<'HTML'
                <div class="mx-2 text-red">
                    <hr/>
                </div>
            HTML);

            $testCaseName = $testResult->testCaseName;
            $description = $testResult->description;

            /** @var class-string $throwableClassName */
            $throwableClassName = $testResult->throwable->className();

            $throwableClassName = ! in_array($throwableClassName, [
                ExpectationFailedException::class,
                IncompleteTestError::class,
            ], true) ? sprintf('<span class="px-1 bg-red font-bold">%s</span>', (new ReflectionClass($throwableClassName))->getShortName())
                : '';

            $truncateClasses = $this->output->isVerbose() ? '' : 'flex-1 truncate';

            renderUsing($this->output);
            render(sprintf(<<<'HTML'
                <div class="flex justify-between mx-2">
                    <span class="%s">
                        <span class="px-1 bg-%s font-bold uppercase">%s</span> <span class="font-bold">%s</span><span class="text-gray mx-1">></span><span>%s</span>
                    </span>
                    <span class="ml-1">
                        %s
                    </span>
                </div>
            HTML, $truncateClasses, $testResult->color, $testResult->type, $testCaseName, $description, $throwableClassName));

            $this->style->writeError($testResult->throwable);
        }, $errors);
    }

    public function recap(\PHPUnit\TestRunner\TestResult\TestResult $testResult, Duration $duration): void
    {
        $testCounts = [
            'passed' => ['green', $testResult->numberOfTestsRun()],
            'failed' => ['red', $testResult->numberOfTestFailedEvents()],
            'errored' => ['red', $testResult->numberOfTestErroredEvents()],
            'skipped' => ['yellow', $testResult->numberOfTestSkippedEvents()],
            'incomplete' => ['yellow', $testResult->numberOfTestMarkedIncompleteEvents()],
            'risky' => ['yellow', $testResult->numberOfTestsWithTestConsideredRiskyEvents()],
            'warnings' => ['yellow', $testResult->numberOfTestsWithTestTriggeredWarningEvents()],
        ];

        $tests = [];

        foreach ($testCounts as $type => [$color, $count]) {
            if ($count === 0) {
                continue;
            }

            $tests[] = "<fg={$color};options=bold>$count $type</>";
        }

        $this->output->writeln(['']);

        if (! empty($tests)) {
            $this->output->writeln([
                sprintf(
                    '  <fg=gray>Tests:</>    <fg=default>%s</><fg=gray> (%s assertions)</>',
                    implode('<fg=gray>,</> ', $tests),
                    $testResult->numberOfAssertions()
                ),
            ]);
        }

        $this->output->writeln([
            sprintf(
                '  <fg=gray>Duration:</> <fg=default>%ss</>',
                number_format($duration->asSeconds(), 2, '.', '')
            ),
        ]);

        $this->output->writeln('');
    }
}
