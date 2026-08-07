<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Exceptions\MissingDependency;
use Pest\Support\Git;

/**
 * @internal
 */
final readonly class ChangedFiles
{
    private Git $git;

    public function __construct(private string $projectRoot)
    {
        $this->git = new Git($projectRoot);
    }

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
        return $this->git->show($sha, $path);
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

        $result = $this->git->result(
            ['check-ignore', '--no-index', '-z', '--stdin'],
            implode("\x00", array_keys($candidates)),
        );

        if ($result['exitCode'] !== 0 && $result['exitCode'] !== 1) {
            throw new MissingDependency('Tia mode', 'git');
        }

        $output = $result['output'];

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
        $output = $this->git->raw(['rev-parse', '--abbrev-ref', 'HEAD']);

        if ($output === null) {
            throw new MissingDependency('Tia mode', 'git');
        }

        $branch = trim($output);

        return $branch === '' || $branch === 'HEAD' ? null : $branch;
    }

    public function defaultBranch(): ?string
    {
        $head = $this->git->output(['symbolic-ref', '--short', 'refs/remotes/origin/HEAD']);

        if ($head !== null) {
            $branch = preg_replace('#^origin/#', '', $head);

            if (is_string($branch) && $branch !== '') {
                return $branch;
            }
        }

        $configured = $this->git->output(['config', '--get', 'init.defaultBranch']);

        if ($configured === null) {
            return null;
        }

        $exists = $this->git->hasRef('refs/heads/'.$configured)
            || $this->git->hasRef('refs/remotes/origin/'.$configured);

        return $exists ? $configured : null;
    }

    /**
     * @return list<string>|null
     */
    public function branchNames(): ?array
    {
        $output = $this->git->raw(['for-each-ref', '--format=%(refname)', 'refs/heads', 'refs/remotes']);

        if ($output === null) {
            return null;
        }

        $names = [];

        foreach ($this->splitLines($output) as $ref) {
            if (str_starts_with($ref, 'refs/heads/')) {
                $names[substr($ref, strlen('refs/heads/'))] = true;

                continue;
            }

            if (! str_starts_with($ref, 'refs/remotes/')) {
                continue;
            }

            $tail = substr($ref, strlen('refs/remotes/'));
            $slash = strpos($tail, '/');

            if ($slash === false) {
                continue;
            }

            $branch = substr($tail, $slash + 1);

            if ($branch !== '' && $branch !== 'HEAD') {
                $names[$branch] = true;
            }
        }

        return array_keys($names);
    }

    public function hasRemote(): bool
    {
        return $this->git->hasRemote();
    }

    public function isRepository(): bool
    {
        return $this->git->isRepository();
    }

    public function hasCommits(): bool
    {
        return $this->git->hasCommits();
    }

    private function scan(): Git
    {
        return $this->git->withTimeout(60.0);
    }

    private function shaIsReachable(string $sha): bool
    {
        return $this->git->succeeds(['merge-base', '--is-ancestor', $sha, 'HEAD']);
    }

    /**
     * @return array<int, string>
     */
    private function diffSinceSha(string $sha): array
    {
        $output = $this->scan()->raw(['diff', '--name-only', '--no-renames', $sha.'..HEAD']);

        if ($output === null) {
            throw new MissingDependency('Tia mode', 'git');
        }

        return $this->splitLines($output);
    }

    /**
     * @return array<int, string>
     */
    private function workingTreeChanges(): array
    {
        $output = $this->scan()->raw(['status', '--porcelain', '-z', '--untracked-files=all']);

        if ($output === null) {
            throw new MissingDependency('Tia mode', 'git');
        }

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
        $output = $this->git->raw(['rev-parse', 'HEAD']);

        if ($output === null) {
            throw new MissingDependency('Tia mode', 'git');
        }

        $sha = trim($output);

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
