<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Symfony\Component\Process\Process;

/**
 * @internal
 */
final readonly class ChangedFiles
{
    public function __construct(private string $projectRoot) {}

    /**
     * @param  array<int, string>  $files  project-relative paths.
     * @param  array<string, string>  $lastRunTree  path → content hash from last run.
     * @return array<int, string>
     */
    public function filterUnchangedSinceLastRun(array $files, array $lastRunTree): array
    {
        if ($lastRunTree === []) {
            return $files;
        }

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
                $remaining[] = $file;

                continue;
            }

            if (! $exists) {
                $remaining[] = $file;

                continue;
            }

            $hash = ContentHash::of($absolute);

            if ($hash === false) {
                $remaining[] = $file;

                continue;
            }

            if ($hash === $snapshot) {
                continue;
            }

            $remaining[] = $file;
        }

        return $remaining;
    }

    /**
     * @param  array<int, string>  $files
     * @return array<string, string> path → xxh128 content hash
     */
    public function snapshotTree(array $files): array
    {
        $out = [];

        foreach ($files as $file) {
            $absolute = $this->projectRoot.DIRECTORY_SEPARATOR.$file;

            if (! is_file($absolute)) {
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
            'bootstrap/cache/',
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
