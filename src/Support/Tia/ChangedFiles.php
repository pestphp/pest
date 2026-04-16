<?php

declare(strict_types=1);

namespace Pest\Support\Tia;

use Symfony\Component\Process\Process;

/**
 * Detects files that changed between the last recorded TIA run and the
 * current working tree.
 *
 * Strategy:
 *   1. If we have a `recordedAtSha`, `git diff <sha>..HEAD` captures committed
 *      changes on top of the recording point.
 *   2. `git status --short` captures unstaged + staged + untracked changes on
 *      top of that.
 *
 * We return relative paths to the project root. Deletions are included so the
 * caller can decide whether to invalidate: a deleted source file may still
 * appear in the graph and should mark its dependents as affected.
 *
 * @internal
 */
final readonly class ChangedFiles
{
    public function __construct(private string $projectRoot) {}

    /**
     * @return array<int, string>|null `null` when git is unavailable, or when
     *                                  the recorded SHA is no longer reachable
     *                                  from HEAD (rebase / force-push) — in
     *                                  that case the graph should be rebuilt.
     */
    public function since(?string $sha): ?array
    {
        if (! $this->gitAvailable()) {
            return null;
        }

        $files = [];

        if ($sha !== null && $sha !== '') {
            if (! $this->shaIsReachable($sha)) {
                return null;
            }

            $files = array_merge($files, $this->diffSinceSha($sha));
        }

        $files = array_merge($files, $this->workingTreeChanges());

        // Normalise + dedupe, filtering out paths that can never belong to the
        // graph: vendor (caught by the fingerprint instead), cache dirs, and
        // anything starting with a dot we don't care about.
        $unique = [];

        foreach ($files as $file) {
            if ($file === '') {
                continue;
            }
            if ($this->shouldIgnore($file)) {
                continue;
            }
            $unique[$file] = true;
        }

        return array_keys($unique);
    }

    private function shouldIgnore(string $path): bool
    {
        static $prefixes = [
            '.pest/',
            '.phpunit.cache/',
            '.phpunit.result.cache',
            'vendor/',
            'node_modules/',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function gitAvailable(): bool
    {
        $process = new Process(['git', 'rev-parse', '--git-dir'], $this->projectRoot);
        $process->run();

        return $process->isSuccessful();
    }

    private function shaIsReachable(string $sha): bool
    {
        $process = new Process(
            ['git', 'merge-base', '--is-ancestor', $sha, 'HEAD'],
            $this->projectRoot,
        );
        $process->run();

        // Exit 0 → ancestor; 1 → not ancestor; anything else → git error
        // (e.g. unknown commit after a rebase/gc). Treat non-zero as
        // "unreachable" and force a rebuild.
        return $process->getExitCode() === 0;
    }

    /**
     * @return array<int, string>
     */
    private function diffSinceSha(string $sha): array
    {
        $process = new Process(
            ['git', 'diff', '--name-only', $sha.'..HEAD'],
            $this->projectRoot,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        return $this->splitLines($process->getOutput());
    }

    /**
     * @return array<int, string>
     */
    private function workingTreeChanges(): array
    {
        // `-z` produces NUL-terminated records with no path quoting, so paths
        // that contain spaces, tabs, unicode or other special characters
        // are passed through verbatim. Without `-z`, git wraps such paths in
        // quotes with backslash escapes, which would corrupt our lookup keys.
        //
        // Record format: `XY <SP> <path> <NUL>` for most entries, and
        // `R  <new> <NUL> <orig> <NUL>` for renames/copies (two NUL-separated
        // fields).
        $process = new Process(
            ['git', 'status', '--porcelain', '-z', '--untracked-files=all'],
            $this->projectRoot,
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

            // Renames/copies emit two records: the new path first, then the
            // original. Consume both.
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

    public function currentSha(): ?string
    {
        if (! $this->gitAvailable()) {
            return null;
        }

        $process = new Process(['git', 'rev-parse', 'HEAD'], $this->projectRoot);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $sha = trim($process->getOutput());

        return $sha === '' ? null : $sha;
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $output): array
    {
        $lines = preg_split('/\R+/', trim($output), flags: PREG_SPLIT_NO_EMPTY);

        return $lines === false ? [] : $lines;
    }
}
