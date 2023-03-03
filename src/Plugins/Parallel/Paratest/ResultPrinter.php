<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Paratest;

use function assert;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use NunoMaduro\Collision\Adapters\Phpunit\State;
use ParaTest\Options;
use Pest\Plugins\Parallel\Support\CompactPrinter;
use Pest\Support\StateGenerator;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Output\Printer;
use function preg_replace;
use SebastianBergmann\Timer\Duration;
use SplFileInfo;
use function strlen;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/** @internal */
final class ResultPrinter
{
    /**
     * If the test should be marked as todo.
     */
    public bool $lastWasTodo = false;

    /**
     * The "native" printer.
     */
    public readonly Printer $printer;

    /**
     * The state.
     */
    public int $passedTests = 0;

    /**
     * The "compact" printer.
     */
    private readonly CompactPrinter $compactPrinter;

    /** @var resource|null */
    private $teamcityLogFileHandle;

    /** @var array<string, int> */
    private array $tailPositions;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Options $options
    ) {
        $this->printer = new class($this->output) implements Printer
        {
            public function __construct(
                private readonly OutputInterface $output,
            ) {
            }

            public function print(string $buffer): void
            {
                $buffer = OutputFormatter::escape($buffer);
                if (str_starts_with($buffer, "\nGenerating code coverage report")) {
                    return;
                }
                if (str_starts_with($buffer, 'done [')) {
                    return;
                }
                $this->output->write(OutputFormatter::escape($buffer));
            }

            public function flush(): void
            {
            }
        };

        $this->compactPrinter = CompactPrinter::default();

        if (! $this->options->configuration->hasLogfileTeamcity()) {
            return;
        }

        $teamcityLogFileHandle = fopen($this->options->configuration->logfileTeamcity(), 'ab+');
        assert($teamcityLogFileHandle !== false);
        $this->teamcityLogFileHandle = $teamcityLogFileHandle;
    }

    /** @param  array<int, SplFileInfo>  $teamcityFiles */
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

        $feedbackItems = (string) preg_replace('/ +\\d+ \\/ \\d+ \\( ?\\d+%\\)\\s*/', '', $feedbackItems);

        $actualTestCount = strlen($feedbackItems);
        for ($index = 0; $index < $actualTestCount; $index++) {
            $this->printFeedbackItem($feedbackItems[$index]);
        }
    }

    /**
     * @param  array<int, SplFileInfo>  $teamcityFiles
     * @param  array<int, SplFileInfo>  $testdoxFiles
     */
    public function printResults(TestResult $testResult, array $teamcityFiles, array $testdoxFiles, Duration $duration): void
    {
        if ($this->options->needsTeamcity) {
            $teamcityProgress = $this->tailMultiple($teamcityFiles);

            if ($this->teamcityLogFileHandle !== null) {
                fwrite($this->teamcityLogFileHandle, $teamcityProgress);
                $resource = $this->teamcityLogFileHandle;
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

        $state = (new StateGenerator())->fromPhpUnitTestResult($this->passedTests, $testResult);

        $this->compactPrinter->errors($state);
        $this->compactPrinter->recap($state, $testResult, $duration, $this->options);
    }

    private function printFeedbackItem(string $item): void
    {
        if ($this->lastWasTodo) {
            $this->lastWasTodo = false;

            return;
        }

        if ($item === 'T') {
            $this->lastWasTodo = true;
        }

        if ($item === '.') {
            $this->passedTests++;
        }

        $this->compactPrinter->descriptionItem($item);
    }

    /** @param  array<int, SplFileInfo>  $files */
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
        $path = $file->getPathname();
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
