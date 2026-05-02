<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Exceptions\MissingDependency;
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
            $current = $this->currentHash($file);

            if ($snapshot === null || $current === null || $current !== $snapshot) {
                $remaining[] = $file;
            }
        }

        return $remaining;
    }

    private function currentHash(string $relativePath): ?string
    {
        $absolute = $this->projectRoot.DIRECTORY_SEPARATOR.$relativePath;

        if (! is_file($absolute)) {
            return null;
        }

        $hash = ContentHash::of($absolute);

        return $hash === false ? null : $hash;
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
            $unique[$file] = true;
        }

        $candidates = array_keys($this->filterIgnored($unique));

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
            $currentHash = $this->currentHash($file);

            if ($currentHash === null) {
                $remaining[] = $file;

                continue;
            }

            $baselineContent = $this->contentAtSha($sha, $file);

            if ($baselineContent === null) {
                $remaining[] = $file;

                continue;
            }

            if ($currentHash !== ContentHash::ofContent($file, $baselineContent)) {
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

    /**
     * @param  array<string, true>  $candidates
     * @return array<string, true>
     */
    private function filterIgnored(array $candidates): array
    {
        if ($candidates === []) {
            return $candidates;
        }

        $process = new Process(
            ['git', 'check-ignore', '--no-index', '-z', '--stdin'],
            $this->projectRoot,
        );
        $process->setTimeout(5.0);
        $process->setInput(implode("\x00", array_keys($candidates)));
        $process->run();

        $exitCode = $process->getExitCode();

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new MissingDependency('Tia mode', 'git');
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

    public function currentBranch(): ?string
    {
        $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $this->projectRoot);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new MissingDependency('Tia mode', 'git');
        }

        $branch = trim($process->getOutput());

        return $branch === '' || $branch === 'HEAD' ? null : $branch;
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
            throw new MissingDependency('Tia mode', 'git');
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
            throw new MissingDependency('Tia mode', 'git');
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
        $process = new Process(['git', 'rev-parse', 'HEAD'], $this->projectRoot);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new MissingDependency('Tia mode', 'git');
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
