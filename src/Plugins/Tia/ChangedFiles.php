<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Exceptions\MissingDependency;
use Pest\Exceptions\TiaGitFailed;
use Pest\Support\Arr;
use Pest\Support\Git;
use Symfony\Component\Process\ExecutableFinder;

/**
 * @internal
 */
final class ChangedFiles
{
    private readonly Git $git;

    private ?string $gitPrefix = null;

    /**
     * @var array<string, true>
     */
    private array $outsideProject = [];

    /**
     * @var array<string, true>
     */
    private array $outsideProjectDirty = [];

    public function __construct(private readonly string $projectRoot)
    {
        $this->git = new Git($projectRoot);
    }

    public function gitPrefix(): string
    {
        return $this->gitPrefix ??= $this->git->pathPrefix();
    }

    /**
     * @param  array<int, string>  $files  repository-relative paths, as printed by git.
     * @return array<int, string> project-relative paths; paths outside the project are dropped.
     */
    private function toProjectRelative(array $files, bool $fromWorkingTree = false): array
    {
        $prefix = $this->gitPrefix();

        if ($prefix === '') {
            return $files;
        }

        $projectFiles = [];

        foreach ($files as $file) {
            if (str_starts_with($file, $prefix)) {
                $projectFiles[] = substr($file, strlen($prefix));

                continue;
            }

            if ($file === '') {
                continue;
            }

            $this->outsideProject[$file] = true;

            if ($fromWorkingTree) {
                $this->outsideProjectDirty[$file] = true;
            }
        }

        return $projectFiles;
    }

    /**
     * @return array<int, string>
     */
    public function outsideProjectDirty(): array
    {
        return Arr::stringKeys($this->outsideProjectDirty);
    }

    /**
     * @return array<int, string>
     */
    public function outsideProject(): array
    {
        return Arr::stringKeys($this->outsideProject);
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

        foreach (Arr::stringKeys($lastRunTree) as $snapshotted) {
            $candidates[$snapshotted] = true;
        }

        $remaining = [];

        foreach (Arr::stringKeys($candidates) as $file) {
            $snapshot = $lastRunTree[$file] ?? null;
            $current = $this->currentHash($file);

            if ($snapshot === null || $current === null || $current !== $snapshot) {
                $remaining[] = $file;
            }
        }

        return $remaining;
    }

    /**
     * @param  array<int, string>  $repositoryRelativeInputs
     * @return array<string, string>
     */
    public function baselineTree(?string $sha, array $repositoryRelativeInputs): array
    {
        return $this->snapshotTree([...($this->since($sha) ?? []), ...$this->externalKeys($repositoryRelativeInputs)]);
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<int, string>  $repositoryRelativeInputs
     * @param  array<string, string>  $lastRunTree
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function changedSinceLastRun(array $files, array $repositoryRelativeInputs, array $lastRunTree): array
    {
        $parent = $this->parentPath();
        $projectFiles = [];
        $externalInputsChanged = [];

        foreach ($this->filterUnchangedSinceLastRun([...$files, ...$this->externalKeys($repositoryRelativeInputs)], $lastRunTree) as $file) {
            if ($parent !== '' && str_starts_with($file, $parent)) {
                $externalInputsChanged[] = substr($file, strlen($parent));
            } else {
                $projectFiles[] = $file;
            }
        }

        return [$projectFiles, $externalInputsChanged];
    }

    /**
     * @param  array<int, string>  $repositoryRelativeInputs
     * @return array<int, string>
     */
    private function externalKeys(array $repositoryRelativeInputs): array
    {
        $parent = $this->parentPath();

        if ($parent === '') {
            return [];
        }

        return array_map(static fn (string $input): string => $parent.$input, $repositoryRelativeInputs);
    }

    private function parentPath(): string
    {
        return str_repeat('../', substr_count($this->gitPrefix(), '/'));
    }

    private function currentHash(string $relativePath): ?string
    {
        $absolute = $this->projectRoot.DIRECTORY_SEPARATOR.$relativePath;

        if (! is_file($absolute)) {
            return null;
        }

        $hash = $this->hashOf($relativePath, $absolute);

        return $hash === false ? null : $hash;
    }

    private function hashOf(string $relativePath, string $absolute): string|false
    {
        $parent = $this->parentPath();

        if ($parent !== '' && str_starts_with($relativePath, $parent)) {
            return @hash_file('xxh128', $absolute);
        }

        return ContentHash::of($absolute);
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

            $hash = $this->hashOf($file, $absolute);

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
        $this->outsideProject = [];
        $this->outsideProjectDirty = [];

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

        $candidates = Arr::stringKeys($this->filterIgnored($unique));

        $this->filterIgnoredOutsideProject();

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
        return $this->git->show($sha, $this->gitPrefix().$path);
    }

    private function filterIgnoredOutsideProject(): void
    {
        if ($this->outsideProject === []) {
            return;
        }

        $repositoryRoot = $this->git->repositoryRoot();

        if ($repositoryRoot === null) {
            return;
        }

        $git = new Git($repositoryRoot);

        $this->outsideProject = $this->filterIgnoredWith($git, $this->outsideProject);
        $this->outsideProjectDirty = array_intersect_key($this->outsideProjectDirty, $this->outsideProject);
    }

    /**
     * @param  array<string, true>  $candidates
     * @return array<string, true>
     */
    private function filterIgnored(array $candidates): array
    {
        return $this->filterIgnoredWith($this->git, $candidates);
    }

    /**
     * @param  array<string, true>  $candidates
     * @return array<string, true>
     */
    private function filterIgnoredWith(Git $git, array $candidates): array
    {
        if ($candidates === []) {
            return $candidates;
        }

        $result = $git->result(
            ['check-ignore', '-z', '--stdin'],
            implode("\x00", array_keys($candidates)),
        );

        if ($result['exitCode'] !== 0 && $result['exitCode'] !== 1) {
            throw $this->gitFailure($result);
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
        $result = $this->git->result(['rev-parse', '--abbrev-ref', 'HEAD']);

        if ($result['exitCode'] !== 0) {
            throw $this->gitFailure($result);
        }

        $branch = trim($result['output']);

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

        return Arr::stringKeys($names);
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
        if ($this->git->succeeds(['merge-base', '--is-ancestor', $sha, 'HEAD'])) {
            return true;
        }

        $parents = $this->git->raw(['show', '--no-patch', '--format=%P', $sha]);

        if ($parents === null) {
            return false;
        }

        $parents = explode(' ', trim($parents));

        if (count($parents) < 2) {
            return false;
        }

        return array_all($parents, fn (string $parent): bool => $this->git->succeeds(['merge-base', '--is-ancestor', $parent, 'HEAD']));
    }

    /**
     * @return array<int, string>
     */
    private function diffSinceSha(string $sha): array
    {
        $result = $this->scan()->result(['diff', '--name-only', '--no-renames', '--no-relative', '-z', $sha.'..HEAD']);

        if ($result['exitCode'] !== 0) {
            throw $this->gitFailure($result);
        }

        return $this->toProjectRelative($this->splitRecords($result['output']));
    }

    /**
     * @return array<int, string>
     */
    private function workingTreeChanges(): array
    {
        $result = $this->scan()->result(['status', '--porcelain', '-z', '--untracked-files=all']);

        if ($result['exitCode'] !== 0) {
            throw $this->gitFailure($result);
        }

        $output = $result['output'];

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

            if (str_contains($status, 'R') || str_contains($status, 'C')) {
                $files[] = $path;

                if (isset($records[$i + 1]) && $records[$i + 1] !== '') {
                    $files[] = $records[$i + 1];
                    $i++;
                }

                continue;
            }

            $files[] = $path;
        }

        return $this->toProjectRelative($files, fromWorkingTree: true);
    }

    public function currentSha(): ?string
    {
        $result = $this->git->result(['rev-parse', 'HEAD']);

        if ($result['exitCode'] !== 0) {
            throw $this->gitFailure($result);
        }

        $sha = trim($result['output']);

        return $sha === '' ? null : $sha;
    }

    /**
     * @param  array{exitCode: int, output: string, errorOutput: string}  $result
     */
    private function gitFailure(array $result): MissingDependency|TiaGitFailed
    {
        $error = trim($result['errorOutput']);

        if ((new ExecutableFinder)->find('git') === null || str_contains($error, 'not a git repository')) {
            return new MissingDependency('Tia mode', 'git');
        }

        return new TiaGitFailed($error !== '' ? $error : sprintf('Git exited with code %d.', $result['exitCode']));
    }

    /**
     * @return array<int, string>
     */
    private function splitRecords(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(
            explode("\x00", rtrim($output, "\x00")),
            static fn (string $record): bool => $record !== '',
        ));
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
