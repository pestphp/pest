<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Paratest;

use ParaTest\Options;
use PHPUnit\Runner\TestSuiteSorter;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Output\Default\ResultPrinter as DefaultResultPrinter;
use PHPUnit\TextUI\Output\Printer;
use PHPUnit\TextUI\Output\SummaryPrinter;
use PHPUnit\Util\Color;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use SplFileInfo;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function fclose;
use function feof;
use function floor;
use function fopen;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function preg_replace;
use function sprintf;
use function str_repeat;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const PHP_VERSION;

/** @internal */
final class ResultPrinter
{
    public readonly Printer $printer;

    private int $numTestsWidth   = 0;
    private int $maxColumn       = 0;
    private int $totalCases      = 0;
    private int $column          = 0;
    private int $casesProcessed  = 0;
    private int $numberOfColumns = 80;
    /** @var resource|null */
    private $teamcityLogFileHandle;
    /** @var array<non-empty-string, int> */
    private array $tailPositions;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Options $options
    ) {
        $this->printer = new class ($this->output) implements Printer {
            public function __construct(
                private readonly OutputInterface $output,
            ) {
            }

            public function print(string $buffer): void
            {
                $this->output->write(OutputFormatter::escape($buffer));
            }

            public function flush(): void
            {
            }
        };

        if (! $this->options->configuration->hasLogfileTeamcity()) {
            return;
        }

        $teamcityLogFileHandle = fopen($this->options->configuration->logfileTeamcity(), 'ab+');
        assert($teamcityLogFileHandle !== false);
        $this->teamcityLogFileHandle = $teamcityLogFileHandle;
    }

    public function setTestCount(int $testCount): void
    {
        $this->totalCases = $testCount;
    }

    public function start(): void
    {
        $this->numTestsWidth = strlen((string) $this->totalCases);
        $this->maxColumn     = $this->numberOfColumns
            + (DIRECTORY_SEPARATOR === '\\' ? -1 : 0) // fix windows blank lines
            - strlen($this->getProgress());

        // @see \PHPUnit\TextUI\TestRunner::writeMessage()
        $output = $this->output;
        $write  = static function (string $type, string $message) use ($output): void {
            $output->write(sprintf("%-15s%s\n", $type . ':', $message));
        };

        // @see \PHPUnit\TextUI\Application::writeRuntimeInformation()
        $write('Processes', (string) $this->options->processes);
    }

    /** @param list<SplFileInfo> $teamcityFiles */
    public function printFeedback(SplFileInfo $progressFile, array $teamcityFiles): void
    {
        if ($this->options->needsTeamcity) {
            $teamcityProgress = $this->tailMultiple($teamcityFiles);

            if ($this->teamcityLogFileHandle !== null) {
                fwrite($this->teamcityLogFileHandle, $teamcityProgress);
            }
        }

        if ($this->options->configuration->outputIsTeamCity()) {
            assert(isset($teamcityProgress));
            $this->output->write($teamcityProgress);

            return;
        }

        if ($this->options->configuration->noProgress()) {
            return;
        }

        $feedbackItems = $this->tail($progressFile);
        if ($feedbackItems === '') {
            return;
        }

        $feedbackItems = preg_replace('/ +\\d+ \\/ \\d+ \\( ?\\d+%\\)\\s*/', '', $feedbackItems);

        $actualTestCount = strlen($feedbackItems);
        for ($index = 0; $index < $actualTestCount; ++$index) {
            $this->printFeedbackItem($feedbackItems[$index]);
        }
    }

    /**
     * @param list<SplFileInfo> $teamcityFiles
     * @param list<SplFileInfo> $testdoxFiles
     */
    public function printResults(TestResult $testResult, array $teamcityFiles, array $testdoxFiles): void
    {
        if ($this->options->needsTeamcity) {
            $teamcityProgress = $this->tailMultiple($teamcityFiles);

            if ($this->teamcityLogFileHandle !== null) {
                fwrite($this->teamcityLogFileHandle, $teamcityProgress);
                $resource                    = $this->teamcityLogFileHandle;
                $this->teamcityLogFileHandle = null;
                fclose($resource);
            }
        }

        if ($this->options->configuration->outputIsTeamCity()) {
            assert(isset($teamcityProgress));
            $this->output->write($teamcityProgress);

            return;
        }

        if ($this->options->configuration->outputIsTestDox()) {
            $this->output->write($this->tailMultiple($testdoxFiles));

            return;
        }

        $resultPrinter  = new DefaultResultPrinter(
            $this->printer,
            $this->options->configuration->displayDetailsOnIncompleteTests(),
            $this->options->configuration->displayDetailsOnSkippedTests(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerDeprecations(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerErrors(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerNotices(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerWarnings(),
            false,
        );
        $summaryPrinter = new SummaryPrinter(
            $this->printer,
            $this->options->configuration->colors(),
        );

        $this->printer->print(PHP_EOL . (new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest() . PHP_EOL . PHP_EOL);

        $resultPrinter->print($testResult);
        $summaryPrinter->print($testResult);
    }

    private function printFeedbackItem(string $item): void
    {
        $this->printFeedbackItemColor($item);
        ++$this->column;
        ++$this->casesProcessed;
        if ($this->column !== $this->maxColumn && $this->casesProcessed < $this->totalCases) {
            return;
        }

        if (
            $this->casesProcessed > 0
            && $this->casesProcessed === $this->totalCases
            && ($pad = $this->maxColumn - $this->column) > 0
        ) {
            $this->output->write(str_repeat(' ', $pad));
        }

        $this->output->write($this->getProgress() . "\n");
        $this->column = 0;
    }

    private function printFeedbackItemColor(string $item): void
    {
        $buffer = match ($item) {
            'E' => $this->colorizeTextBox('fg-red, bold', $item),
            'F' => $this->colorizeTextBox('bg-red, fg-white', $item),
            'I', 'N', 'D', 'R', 'W' => $this->colorizeTextBox('fg-yellow, bold', $item),
            'S' => $this->colorizeTextBox('fg-cyan, bold', $item),
            default => $item,
        };

        $this->output->write($buffer);
    }

    private function getProgress(): string
    {
        return sprintf(
            ' %' . $this->numTestsWidth . 'd / %' . $this->numTestsWidth . 'd (%3s%%)',
            $this->casesProcessed,
            $this->totalCases,
            floor(($this->totalCases > 0 ? $this->casesProcessed / $this->totalCases : 0) * 100),
        );
    }

    private function colorizeTextBox(string $color, string $buffer): string
    {
        if (! $this->options->configuration->colors()) {
            return $buffer;
        }

        return Color::colorizeTextBox($color, $buffer);
    }

    /** @param list<SplFileInfo> $files */
    private function tailMultiple(array $files): string
    {
        $content = '';
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $content .= $this->tail($file);
        }

        return $content;
    }

    private function tail(SplFileInfo $file): string
    {
        $path   = $file->getPathname();
        $handle = fopen($path, 'r');
        assert($handle !== false);
        $fseek = fseek($handle, $this->tailPositions[$path] ?? 0);
        assert($fseek === 0);

        $contents = '';
        while (! feof($handle)) {
            $fread = fread($handle, 8192);
            assert($fread !== false);
            $contents .= $fread;
        }

        $ftell = ftell($handle);
        assert($ftell !== false);
        $this->tailPositions[$path] = $ftell;
        fclose($handle);

        return $contents;
    }
}
