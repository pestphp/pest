<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\Directory;
use SebastianBergmann\CodeCoverage\Node\File;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @internal
 */
final class Coverage
{
    /**
     * Returns the coverage path.
     */
    public static function getPath(): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            dirname(__DIR__, 2),
            '.temp',
            'coverage.php',
        ]);
    }

    /**
     * Runs true there is any code coverage driver available.
     */
    public static function isAvailable(): bool
    {
        return (new Runtime())->canCollectCodeCoverage();
    }

    /**
     * If the user is using Xdebug.
     */
    public static function usingXdebug(): bool
    {
        return (new Runtime())->hasXdebug();
    }

    /**
     * Reports the code coverage report to the
     * console and returns the result in float.
     */
    public static function report(OutputInterface $output): float
    {
        if (!file_exists($reportPath = self::getPath())) {
            if (self::usingXdebug()) {
                $output->writeln(
                    "  <fg=black;bg=yellow;options=bold> WARN </> Unable to get coverage using Xdebug. Did you set <href=https://xdebug.org/docs/code_coverage#mode>Xdebug's coverage mode</>?</>",
                );

                return 0.0;
            }

            throw ShouldNotHappen::fromMessage(sprintf('Coverage not found in path: %s.', $reportPath));
        }

        /** @var CodeCoverage $codeCoverage */
        $codeCoverage = require $reportPath;
        unlink($reportPath);

        $totalWidth = (new Terminal())->getWidth();

        $dottedLineLength = $totalWidth <= 70 ? $totalWidth : 70;

        $totalCoverage = $codeCoverage->getReport()->percentageOfExecutedLines();

        $output->writeln(
            sprintf(
                '  <fg=white;options=bold>Cov:    </><fg=default>%s</>',
                $totalCoverage->asString()
            )
        );

        $output->writeln('');

        /** @var Directory<File|Directory> $report */
        $report = $codeCoverage->getReport();

        foreach ($report->getIterator() as $file) {
            if (!$file instanceof File) {
                continue;
            }
            $dirname  = dirname($file->id());
            $basename = basename($file->id(), '.php');

            $name = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [
                $dirname,
                $basename,
            ]);
            $rawName = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [
                $dirname,
                $basename,
            ]);

            $linesExecutedTakenSize = 0;

            if ($file->percentageOfExecutedLines()->asString() != '0.00%') {
                $linesExecutedTakenSize = strlen($uncoveredLines = trim(implode(', ', self::getMissingCoverage($file)))) + 1;
                $name .= sprintf(' <fg=red>%s</>', $uncoveredLines);
            }

            $percentage = $file->numberOfExecutableLines() === 0
                ? '100.0'
                : number_format($file->percentageOfExecutedLines()->asFloat(), 1, '.', '');

            $takenSize = strlen($rawName . $percentage) + 4 + $linesExecutedTakenSize; // adding 3 space and percent sign

            $percentage = sprintf(
                '<fg=%s>%s</>',
                $percentage === '100.0' ? 'green' : ($percentage === '0.0' ? 'red' : 'yellow'),
                $percentage
            );

            $output->writeln(sprintf(
                '  %s %s %s %%',
                $name,
                str_repeat('.', max($dottedLineLength - $takenSize, 1)),
                $percentage
            ));
        }

        return $totalCoverage->asFloat();
    }

    /**
     * Generates an array of missing coverage on the following format:.
     *
     * ```
     * ['11', '20..25', '50', '60..80'];
     * ```
     *
     * @param File $file
     *
     * @return array<int, string>
     */
    public static function getMissingCoverage($file): array
    {
        $shouldBeNewLine = true;

        $eachLine = function (array $array, array $tests, int $line) use (&$shouldBeNewLine): array {
            if (count($tests) > 0) {
                $shouldBeNewLine = true;

                return $array;
            }

            if ($shouldBeNewLine) {
                $array[]         = (string) $line;
                $shouldBeNewLine = false;

                return $array;
            }

            $lastKey = count($array) - 1;

            if (array_key_exists($lastKey, $array) && strpos($array[$lastKey], '..') !== false) {
                [$from]          = explode('..', $array[$lastKey]);
                $array[$lastKey] = $line > $from ? sprintf('%s..%s', $from, $line) : sprintf('%s..%s', $line, $from);

                return $array;
            }

            $array[$lastKey] = sprintf('%s..%s', $array[$lastKey], $line);

            return $array;
        };

        $array = [];
        foreach (array_filter($file->lineCoverageData(), 'is_array') as $line => $tests) {
            $array = $eachLine($array, $tests, $line);
        }

        return $array;
    }
}
