<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;
use Pest\Plugins\Tia\CoverageMerger;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\Directory;
use SebastianBergmann\CodeCoverage\Node\File;
use SebastianBergmann\CodeCoverage\Util\Percentage;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
     * Resolve changed PHP files for branch-scoped coverage (working tree vs merge-base with upstream or a fallback mainline ref).
     *
     * `lineSetsByNormalizedPath` maps normalized paths to 1-based lines from `git diff -U0 <merge-base>` (additions). `null` means show all uncovered lines in that file (e.g. path from index/status only).
     *
     * @return array{ok: bool, absolutePhpPaths: list<string>, lineSetsByNormalizedPath: array<string, array<int, true>|null>, errorMessage: ?string}
     */
    public static function resolveBranchChangedPhpPaths(string $projectRoot): array
    {
        if (! is_dir($projectRoot.DIRECTORY_SEPARATOR.'.git') && ! is_file($projectRoot.DIRECTORY_SEPARATOR.'.git')) {
            return [
                'ok' => false,
                'absolutePhpPaths' => [],
                'lineSetsByNormalizedPath' => [],
                'errorMessage' => 'Branch-scoped coverage requires a Git repository.',
            ];
        }

        $mergeBaseSha = self::coverageMergeBaseSha($projectRoot);

        if ($mergeBaseSha === null) {
            return [
                'ok' => false,
                'absolutePhpPaths' => [],
                'lineSetsByNormalizedPath' => [],
                'errorMessage' => 'Branch-scoped coverage could not resolve a base: set an upstream for this branch (e.g. git branch -u origin/feature-1) or ensure origin/main, origin/master, main, or master exists.',
            ];
        }

        $diffText = self::coverageGitDiffUnifiedZeroFromMergeBase($projectRoot, $mergeBaseSha);
        $linesByRelativePosix = self::coverageParseUnifiedDiffZero($diffText);

        $paths = array_merge(
            array_keys($linesByRelativePosix),
            self::coverageDiffNameOnlyMergeBase($projectRoot, $mergeBaseSha),
            self::coverageDiffCachedNameOnly($projectRoot),
            self::coverageWorkingTreeChanges($projectRoot),
        );

        $unique = [];

        foreach ($paths as $file) {
            if ($file !== '') {
                $unique[$file] = true;
            }
        }

        $candidates = array_keys(self::coverageFilterIgnored($projectRoot, $unique));

        $absolutePhpPaths = [];
        $lineSetsByNormalizedPath = [];

        foreach ($candidates as $relative) {
            if (! str_ends_with(strtolower($relative), '.php')) {
                continue;
            }

            $absolute = $projectRoot.DIRECTORY_SEPARATOR.$relative;

            if (! is_file($absolute)) {
                continue;
            }

            $real = realpath($absolute);

            if ($real === false) {
                continue;
            }

            $norm = self::normalizePathForCoverageLookup($real);
            $absolutePhpPaths[$norm] = true;

            $relPosix = str_replace('\\', '/', $relative);
            $lineSet = $linesByRelativePosix[$relPosix] ?? [];

            if ($lineSet === []) {
                $lineSetsByNormalizedPath[$norm] = null;
            } else {
                $lineSetsByNormalizedPath[$norm] = $lineSet;
            }
        }

        return [
            'ok' => true,
            'absolutePhpPaths' => array_keys($absolutePhpPaths),
            'lineSetsByNormalizedPath' => $lineSetsByNormalizedPath,
            'errorMessage' => null,
        ];
    }

    /**
     * Normalizes paths so Git-derived paths and php-code-coverage {@see File} paths compare reliably.
     */
    private static function normalizePathForCoverageLookup(string $path): string
    {
        $resolved = realpath($path);

        if ($resolved !== false) {
            $path = $resolved;
        }

        return str_replace('\\', '/', $path);
    }

    private static function coverageHasUpstream(string $projectRoot): bool
    {
        $upstreamCheck = new Process(
            ['git', 'rev-parse', '--verify', '@{upstream}^{commit}'],
            $projectRoot,
        );
        $upstreamCheck->run();

        return $upstreamCheck->isSuccessful();
    }

    /**
     * Merge-base of HEAD with upstream when set, otherwise with the first resolvable heuristic mainline ref.
     */
    private static function coverageMergeBaseSha(string $projectRoot): ?string
    {
        if (self::coverageHasUpstream($projectRoot)) {
            $mergeBaseProcess = new Process(
                ['git', 'merge-base', '@{upstream}', 'HEAD'],
                $projectRoot,
            );
            $mergeBaseProcess->run();

            if (! $mergeBaseProcess->isSuccessful()) {
                return null;
            }

            $sha = trim($mergeBaseProcess->getOutput());

            return $sha !== '' ? $sha : null;
        }

        $mainlineRefName = self::coverageResolveMainlineRefName($projectRoot);

        if ($mainlineRefName === null) {
            return null;
        }

        $mergeBaseProcess = new Process(
            ['git', 'merge-base', 'HEAD', $mainlineRefName],
            $projectRoot,
        );
        $mergeBaseProcess->run();

        if (! $mergeBaseProcess->isSuccessful()) {
            return null;
        }

        $sha = trim($mergeBaseProcess->getOutput());

        return $sha !== '' ? $sha : null;
    }

    /**
     * @return array<int, string>
     */
    private static function coverageDiffNameOnlyMergeBase(string $projectRoot, string $mergeBaseSha): array
    {
        $process = new Process(
            ['git', 'diff', '--name-only', $mergeBaseSha],
            $projectRoot,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $out = trim($process->getOutput());

        if ($out === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $out, flags: PREG_SPLIT_NO_EMPTY);

        return $lines === false ? [] : $lines;
    }

    /**
     * Working tree vs merge-base: raw unified diff with zero context lines (for touched line numbers).
     */
    private static function coverageGitDiffUnifiedZeroFromMergeBase(string $projectRoot, string $mergeBaseSha): string
    {
        $process = new Process(
            ['git', 'diff', '-U0', $mergeBaseSha],
            $projectRoot,
        );
        $process->run();

        return $process->getOutput();
    }

    /**
     * @return array<string, array<int, true>> project-relative paths using forward slashes
     */
    private static function coverageParseUnifiedDiffZero(string $diff): array
    {
        /** @var array<string, array<int, true>> $byPath */
        $byPath = [];
        $lines = preg_split('/\R/', $diff) ?: [];

        $file = null;
        $newLine = null;

        foreach ($lines as $raw) {
            if (str_starts_with($raw, 'diff --git ')) {
                $file = null;
                $newLine = null;

                if (preg_match('#^diff --git a/\S+ b/(.+)$#', $raw, $m) === 1) {
                    $file = str_replace('\\', '/', $m[1]);
                    $byPath[$file] ??= [];
                }

                continue;
            }

            if ($file === null) {
                continue;
            }

            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $raw, $m) === 1) {
                $newLine = (int) $m[1];

                continue;
            }

            if ($newLine === null) {
                continue;
            }

            if ($raw === '' || str_starts_with($raw, 'Binary files ') || str_starts_with($raw, 'new file mode') || str_starts_with($raw, 'deleted file mode') || str_starts_with($raw, 'similarity index ')) {
                continue;
            }

            if (str_starts_with($raw, '--- ') || str_starts_with($raw, '+++ ') || str_starts_with($raw, 'index ')) {
                continue;
            }

            if (preg_match('/^\\\ No newline/', $raw) === 1) {
                continue;
            }

            $c = $raw[0] ?? '';

            if ($c === ' ') {
                $newLine++;

                continue;
            }

            if ($c === '-') {
                continue;
            }

            if ($c === '+') {
                $byPath[$file][$newLine] = true;
                $newLine++;

                continue;
            }
        }

        return $byPath;
    }

    private static function coverageResolveMainlineRefName(string $projectRoot): ?string
    {
        foreach (['origin/main', 'origin/master', 'main', 'master'] as $candidate) {
            $process = new Process(
                ['git', 'rev-parse', '--verify', $candidate.'^{commit}'],
                $projectRoot,
            );
            $process->run();

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function coverageDiffCachedNameOnly(string $projectRoot): array
    {
        $process = new Process(
            ['git', 'diff', '--cached', '--name-only'],
            $projectRoot,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $out = trim($process->getOutput());

        if ($out === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $out, flags: PREG_SPLIT_NO_EMPTY);

        return $lines === false ? [] : $lines;
    }

    /**
     * @return array<int, string>
     */
    private static function coverageWorkingTreeChanges(string $projectRoot): array
    {
        $process = new Process(
            ['git', 'status', '--porcelain', '-z', '--untracked-files=all'],
            $projectRoot,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = $process->getOutput();

        if ($output === '') {
            return [];
        }

        $records = explode("\x00", rtrim($output, "\x00"));
        $files = [];
        $count = count($records);

        for ($i = 0; $i < $count; $i++) {
            $record = $records[$i];

            if (strlen($record) < 4) {
                continue;
            }

            $status = substr($record, 0, 2);
            $path = substr($record, 3);

            if ($status[0] === 'R' || $status[0] === 'C') {
                $files[] = $path;

                if (isset($records[$i + 1]) && $records[$i + 1] !== '') {
                    $files[] = $records[$i + 1];
                    $i++;
                }

                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * @param  array<string, true>  $candidates
     * @return array<string, true>
     */
    private static function coverageFilterIgnored(string $projectRoot, array $candidates): array
    {
        if ($candidates === []) {
            return $candidates;
        }

        $process = new Process(
            ['git', 'check-ignore', '--no-index', '-z', '--stdin'],
            $projectRoot,
        );
        $process->setInput(implode("\x00", array_keys($candidates)));
        $process->run();

        $exitCode = $process->getExitCode();

        if ($exitCode !== 0 && $exitCode !== 1) {
            return $candidates;
        }

        $output = $process->getOutput();

        if ($output === '') {
            return $candidates;
        }

        foreach (explode("\x00", rtrim($output, "\x00")) as $ignored) {
            if ($ignored !== '') {
                unset($candidates[$ignored]);
            }
        }

        return $candidates;
    }

    /**
     * Reports the code coverage report to the
     * console and returns the result in float.
     *
     * @param  array<int, string>|null  $onlyChangedAbsolutePaths  When non-null, restrict the report to these absolute file paths (branch-scoped coverage).
     * @param  array<string, array<int, true>|null>|null  $onlyChangedLineSetsByNormalizedPath  When set with branch-scoped paths, limit uncovered-line hints to diff-touched lines (null per file = all uncovered lines in that file).
     */
    public static function report(OutputInterface $output, bool $compact = false, bool $showOnlyCovered = false, ?array $onlyChangedAbsolutePaths = null, ?array $onlyChangedLineSetsByNormalizedPath = null): float
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

        CoverageMerger::applyIfMarked($reportPath);

        /** @var CodeCoverage $codeCoverage */
        $codeCoverage = require $reportPath;
        unlink($reportPath);

        /** @var Directory<File|Directory> $report */
        $report = $codeCoverage->getReport();

        $allowSet = null;

        if ($onlyChangedAbsolutePaths !== null) {
            if ($onlyChangedAbsolutePaths === []) {
                $output->writeln([
                    '',
                    '  <fg=black;bg=yellow;options=bold> WARN </> Branch-scoped coverage: no changed PHP files were found compared to the default branch.</>',
                    '',
                ]);

                return 0.0;
            }

            /** @var array<string, true> $allowSet */
            $allowSet = [];

            foreach ($onlyChangedAbsolutePaths as $path) {
                $allowSet[self::normalizePathForCoverageLookup($path)] = true;
            }
        }

        $subsetExecutable = 0;
        $subsetExecuted = 0;
        $matchedChangedInReport = 0;

        foreach ($report->getIterator() as $file) {
            if (! $file instanceof File) {
                continue;
            }

            $filePath = self::normalizePathForCoverageLookup($file->pathAsString());

            if ($allowSet !== null && ! isset($allowSet[$filePath])) {
                continue;
            }

            $changedLineSet = null;

            if ($allowSet !== null && $onlyChangedLineSetsByNormalizedPath !== null && array_key_exists($filePath, $onlyChangedLineSetsByNormalizedPath)) {
                $changedLineSet = $onlyChangedLineSetsByNormalizedPath[$filePath];
            }

            if ($allowSet !== null) {
                $matchedChangedInReport++;
                $subsetExecutable += $file->numberOfExecutableLines();
                $subsetExecuted += $file->numberOfExecutedLines();
            }

            $dirname = dirname($file->id());
            $basename = basename($file->id(), '.php');

            $name = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [
                $dirname,
                $basename,
            ]);

            if ($showOnlyCovered && $file->percentageOfExecutedLines()->asFloat() === 0.0) {
                continue;
            }

            $percentage = $file->numberOfExecutableLines() === 0
                ? '100.0'
                : number_format($file->percentageOfExecutedLines()->asFloat(), 1, '.', '');

            if ($percentage === '100.0' && $compact) {
                continue;
            }

            $uncoveredLines = '';

            $percentageOfExecutedLinesAsString = $file->percentageOfExecutedLines()->asString();

            if (! in_array($percentageOfExecutedLinesAsString, ['0.00%', '100.00%', '100.0%', ''], true)) {
                if (is_array($changedLineSet) && $changedLineSet !== []) {
                    $uncoveredLines = trim(implode(', ', self::getMissingCoverage($file, $changedLineSet)));
                } else {
                    $uncoveredLines = trim(implode(', ', self::getMissingCoverage($file)));
                }
            }

            if ($uncoveredLines !== '') {
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

        if ($allowSet !== null) {
            if ($matchedChangedInReport === 0) {
                $output->writeln([
                    '',
                    '  <fg=black;bg=yellow;options=bold> WARN </> Branch-scoped coverage: none of the changed PHP files appear in this coverage report. Check your PHPUnit path filter and source paths.</>',
                    '',
                ]);

                return 0.0;
            }

            $totalCoverage = Percentage::fromFractionAndTotal((float) $subsetExecuted, (float) $subsetExecutable);
        } else {
            $totalCoverage = $report->percentageOfExecutedLines();
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
     * When `$limitToUncoveredLines` is set, only uncovered executable lines whose number is in that map are listed,
     * but line grouping follows the same rules as the full report (gaps from other uncovered lines still break ranges).
     *
     * @param  mixed  $file
     * @param  array<int, true>|null  $limitToUncoveredLines
     * @return array<int, string>
     */
    public static function getMissingCoverage(mixed $file, ?array $limitToUncoveredLines = null): array
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
            if ($tests !== []) {
                $array = $eachLine($array, $tests, $line);

                continue;
            }

            if ($limitToUncoveredLines === null || isset($limitToUncoveredLines[$line])) {
                $array = $eachLine($array, [], $line);

                continue;
            }

            $array = $eachLine($array, ['__gap__'], $line);
        }

        return $array;
    }
}
