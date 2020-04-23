<?php

declare(strict_types=1);

namespace Pest\Console;

use Pest\Exceptions\ShouldNotHappen;
use SebastianBergmann\CodeCoverage\Node\File;
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
            dirname(__DIR__, 1),
            'temp',
            'coverage.php',
        ]);
    }

    public static function show(OutputInterface $output): void
    {
        if (!file_exists($reportPath = self::getPath())) {
            throw ShouldNotHappen::fromMessage(sprintf('Coverage not found in path: %s.', $reportPath));
        }

        /** @var \SebastianBergmann\CodeCoverage\CodeCoverage $codeCoverage */
        $codeCoverage = require $reportPath;
        unlink($reportPath);

        $totalWidth = (new Terminal())->getWidth();

        $dottedLineLength = $totalWidth <= 70 ? $totalWidth : 70;

        $output->writeln(
            sprintf(
                '  <fg=white;options=bold>Cov:    </><fg=default>%s</>',
                $codeCoverage->getReport()->getLineExecutedPercent()
            )
        );

        $output->writeln('');

        /** @var \SebastianBergmann\CodeCoverage\Node\Directory $report */
        $report = $codeCoverage->getReport();
        /** @var \SebastianBergmann\CodeCoverage\Node\File|\SebastianBergmann\CodeCoverage\Node\Directory $file */
        foreach ($report->getIterator() as $file) {
            if (!$file instanceof File) {
                continue;
            }
            $dirname  = dirname($file->getId());
            $basename = basename($file->getId(), '.php');

            $name = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [
                $dirname,
                $basename,
            ]);
            $rawName = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [
                $dirname,
                $basename,
            ]);

            $linesExecutedTakenSize = 0;

            if ($file->getLineExecutedPercent() != '0.00%') {
                $linesExecutedTakenSize = strlen($uncoveredLines = trim(implode(', ', self::getMissingCoverage($file)))) + 1;
                $name .= sprintf(' <fg=red>%s</>', $uncoveredLines);
            }

            if ($file->getNumExecutableLines() === 0) {
                $percentage = '100.0';
            } else {
                $percentage = number_format((float) $file->getLineExecutedPercent(), 1, '.', '');
            }

            $takenSize  = strlen($rawName . $percentage) + 4 + $linesExecutedTakenSize; // adding 3 space and percent sign

            $percentage = sprintf(
                '<fg=%s>%s</>',
                $percentage === '100.0' ? 'green' : ($percentage === '0.0' ? 'red' : 'yellow'),
                $percentage
            );

            $output->writeln(sprintf('  %s %s %s %%',
                $name,
                str_repeat('.', max($dottedLineLength - $takenSize, 1)),
                $percentage
            ));
        }
    }

    /**
     * Generates an array of missing coverage on the following format:.
     *
     * ```
     * ['11', '20..25', '50', '60...80'];
     * ```
     *
     * @param File $file
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
                $array[$lastKey] = sprintf('%s..%s', $from, $line);

                return $array;
            }

            $array[$lastKey] = sprintf('%s..%s', $array[$lastKey], $line);

            return $array;
        };

        $array = [];
        foreach (array_filter($file->getCoverageData(), 'is_array') as $line => $tests) {
            $array = $eachLine($array, $tests, $line);
        }

        return $array;
    }
}
