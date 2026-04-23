<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

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
     *                                 the recorded SHA is no longer reachable
     *                                 from HEAD (rebase / force-push) — in
     *                                 that case the graph should be rebuilt.
     */
    /**
     * Removes files whose current content hash matches the snapshot from the
     * last `--tia` run. Used to ignore "dirty but unchanged" files — a file
     * that git still reports as modified but whose content is bit-identical
     * to the previous TIA invocation.
     *
     * @param  array<int, string>  $files  project-relative paths.
     * @param  array<string, string>  $lastRunTree  path → content hash from last run.
     * @return array<int, string>
     */
    public function filterUnchangedSinceLastRun(array $files, array $lastRunTree): array
    {
        if ($lastRunTree === []) {
            return $files;
        }

        // Union: `$files` (what git currently reports) + every path that was
        // dirty last run. The second set matters for reverts — when a user
        // undoes a local edit, the file matches HEAD again and git reports
        // it clean, so it would never enter `$files`. But it has genuinely
        // changed vs the snapshot we captured during the bad run, so it
        // must be checked.
        $candidates = array_fill_keys($files, true);

        foreach (array_keys($lastRunTree) as $snapshotted) {
            $candidates[$snapshotted] = true;
        }

        $remaining = [];

        foreach (array_keys($candidates) as $file) {
            $snapshot = $lastRunTree[$file] ?? null;
            $absolute = $this->projectRoot.DIRECTORY_SEPARATOR.$file;
            $exists = is_file($absolute);

            if ($snapshot === null) {
                // File wasn't in last-run tree at all — trust git's signal.
                $remaining[] = $file;

                continue;
            }

            if (! $exists) {
                // Missing now. If the snapshot recorded it as absent too
                // (sentinel ''), state is identical to last run — unchanged.
                // Otherwise it was present last run and got deleted since.
                if ($snapshot !== '') {
                    $remaining[] = $file;
                }

                continue;
            }

            $hash = ContentHash::of($absolute);

            if ($hash === false || $hash !== $snapshot) {
                $remaining[] = $file;
            }
        }

        return $remaining;
    }

    /**
     * Computes content hashes for the given project-relative files. Used to
     * snapshot the working tree after a successful run so the next run can
     * detect which files are actually different.
     *
     * @param  array<int, string>  $files
     * @return array<string, string> path → xxh128 content hash
     */
    public function snapshotTree(array $files): array
    {
        $out = [];

        foreach ($files as $file) {
            $absolute = $this->projectRoot.DIRECTORY_SEPARATOR.$file;

            if (! is_file($absolute)) {
                // Record the deletion with an empty-string sentinel so the
                // next run recognises "still deleted" as unchanged rather
                // than re-flagging the file as a fresh change.
                $out[$file] = '';

                continue;
            }

            $hash = ContentHash::of($absolute);

            if ($hash !== false) {
                $out[$file] = $hash;
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>|null `null` when git is unavailable, or when
     *                                 the recorded SHA is no longer reachable
     *                                 from HEAD (rebase / force-push).
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

        $candidates = array_keys($unique);

        // Behavioural de-noising: for every file git calls "changed", hash
        // the current content and the content at `$sha` through
        // `ContentHash::of()`. A change that only touched comments /
        // whitespace / blade `{{-- --}}` blocks produces the same hash on
        // both sides and gets dropped before it can invalidate any test.
        // Without this, a single-comment edit on a migration re-runs the
        // entire DB-touching suite.
        if ($sha !== null && $sha !== '') {
            return $this->filterBehaviourallyUnchanged($candidates, $sha);
        }

        return $candidates;
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, string>
     */
    private function filterBehaviourallyUnchanged(array $files, string $sha): array
    {
        $remaining = [];

        foreach ($files as $file) {
            $absolute = $this->projectRoot.DIRECTORY_SEPARATOR.$file;

            if (! is_file($absolute)) {
                // Deleted on disk — a genuine change, keep it.
                $remaining[] = $file;

                continue;
            }

            $currentHash = ContentHash::of($absolute);

            if ($currentHash === false) {
                $remaining[] = $file;

                continue;
            }

            $baselineContent = $this->contentAtSha($sha, $file);

            if ($baselineContent === null) {
                // Couldn't read the baseline (new file, binary, `git show`
                // failed). Err on the side of re-running.
                $remaining[] = $file;

                continue;
            }

            $baselineHash = ContentHash::ofContent($file, $baselineContent);

            if ($currentHash !== $baselineHash) {
                $remaining[] = $file;
            }
        }

        return $remaining;
    }

    /**
     * Reads `$path` at `$sha` via `git show`. Returns null when the file
     * didn't exist at that SHA, when git errors, or when the content
     * isn't valid UTF-8-safe bytes (rare — binary files that happen to
     * be tracked).
     */
    private function contentAtSha(string $sha, string $path): ?string
    {
        $process = new Process(['git', 'show', $sha.':'.$path], $this->projectRoot);
        $process->setTimeout(5.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
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

    public function currentBranch(): ?string
    {
        if (! $this->gitAvailable()) {
            return null;
        }

        $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $this->projectRoot);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        return $branch === '' || $branch === 'HEAD' ? null : $branch;
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
