<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\Directory;
use SebastianBergmann\CodeCoverage\Node\File;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;
use function Termwind\renderUsing;
use function Termwind\terminal;

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
        $runtime = new Runtime;

        if (! $runtime->canCollectCodeCoverage()) {
            return false;
        }

        if ($runtime->hasPCOV()) {
            return true;
        }

        if ($runtime->hasPHPDBGCodeCoverage()) {
            return true;
        }

        if (! $runtime->hasXdebug()) {
            return true;
        }

        if (! version_compare((string) phpversion('xdebug'), '3.1', '>=')) {
            return true;
        }

        return in_array('coverage', xdebug_info('mode'), true);
    }

    /**
     * If the user is using Xdebug.
     */
    public static function usingXdebug(): bool
    {
        return (new Runtime)->hasXdebug();
    }

    /**
     * Reports the code coverage report to the
     * console and returns the result in float.
     */
    public static function report(OutputInterface $output): float
    {
        if (! file_exists($reportPath = self::getPath())) {
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

        $totalCoverage = $codeCoverage->getReport()->percentageOfExecutedLines();

        /** @var Directory<File|Directory> $report */
        $report = $codeCoverage->getReport();

        foreach ($report->getIterator() as $file) {
            if (! $file instanceof File) {
                continue;
            }
            $dirname = dirname($file->id());
            $basename = basename($file->id(), '.php');

            $name = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [
                $dirname,
                $basename,
            ]);

            $percentage = $file->numberOfExecutableLines() === 0
                ? '100.0'
                : number_format($file->percentageOfExecutedLines()->asFloat(), 1, '.', '');

            $uncoveredLines = '';

            $percentageOfExecutedLinesAsString = $file->percentageOfExecutedLines()->asString();

            if (! in_array($percentageOfExecutedLinesAsString, ['0.00%', '100.00%', '100.0%', ''], true)) {
                $uncoveredLines = trim(implode(', ', self::getMissingCoverage($file)));
                $uncoveredLines = sprintf('<span>%s</span>', $uncoveredLines).' <span class="text-gray"> / </span>';
            }

            $color = $percentage === '100.0' ? 'green' : ($percentage === '0.0' ? 'red' : 'yellow');

            $truncateAt = max(1, terminal()->width() - 12);

            renderUsing($output);
            render(<<<HTML
                <div class="flex mx-2">
                    <span class="truncate-{$truncateAt}">{$name}</span>
                    <span class="flex-1 content-repeat-[.] text-gray mx-1"></span>
                    <span class="text-{$color}">$uncoveredLines {$percentage}%</span>
                </div>
            HTML);
        }

        $totalCoverageAsString = $totalCoverage->asFloat() === 0.0
            ? '0.0'
            : number_format(floor($totalCoverage->asFloat() * 10) / 10, 1, '.', '');

        renderUsing($output);
        render(<<<HTML
            <div class="mx-2">
                <hr class="text-gray" />
                <div class="w-full text-right">
                    <span class="ml-1 font-bold">Total: {$totalCoverageAsString} %</span>
                </div>
            </div>
        HTML);

        return $totalCoverage->asFloat();
    }

    /**
     * Generates an array of missing coverage on the following format:.
     *
     * ```
     * ['11', '20..25', '50', '60..80'];
     * ```
     *
     *
     * @param  File  $file
     * @return array<int, string>
     */
    public static function getMissingCoverage(mixed $file): array
    {
        $shouldBeNewLine = true;

        $eachLine = function (array $array, array $tests, int $line) use (&$shouldBeNewLine): array {
            if ($tests !== []) {
                $shouldBeNewLine = true;

                return $array;
            }

            if ($shouldBeNewLine) {
                $array[] = (string) $line;
                $shouldBeNewLine = false;

                return $array;
            }

            $lastKey = count($array) - 1;

            if (array_key_exists($lastKey, $array) && str_contains((string) $array[$lastKey], '..')) {
                [$from] = explode('..', (string) $array[$lastKey]);
                $array[$lastKey] = $line > $from ? sprintf('%s..%s', $from, $line) : sprintf('%s..%s', $line, $from);

                return $array;
            }

            $array[$lastKey] = sprintf('%s..%s', $array[$lastKey], $line);

            return $array;
        };

        $array = [];
        foreach (array_filter($file->lineCoverageData(), is_array(...)) as $line => $tests) {
            $array = $eachLine($array, $tests, $line);
        }

        return $array;
    }
}
