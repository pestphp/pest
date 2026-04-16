<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * File-level Test Impact Analysis graph.
 *
 * Persists the mapping `test_file → set<source_file>` so that subsequent runs
 * can skip tests whose dependencies have not changed. Paths are stored relative
 * to the project root and source files are deduplicated via an index so that
 * the on-disk JSON stays compact for large suites.
 *
 * @internal
 */
final class Graph
{
    /**
     * Relative path of each known source file, indexed by numeric id.
     *
     * @var array<int, string>
     */
    private array $files = [];

    /**
     * Reverse lookup: source file → numeric id.
     *
     * @var array<string, int>
     */
    private array $fileIds = [];

    /**
     * Edges: test file (relative) → list of source file ids.
     *
     * @var array<string, array<int, int>>
     */
    private array $edges = [];

    /**
     * Environment fingerprint captured at record time.
     *
     * @var array<string, mixed>
     */
    private array $fingerprint = [];

    /**
     * Commit SHA the graph was recorded against (if in a git repo).
     */
    private ?string $recordedAtSha = null;

    /**
     * Canonicalised project root. Resolved through `realpath()` so paths
     * captured by coverage drivers (always real filesystem targets) match
     * regardless of whether the user's CWD is a symlink or has trailing
     * separators.
     */
    private readonly string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $real = @realpath($projectRoot);

        $this->projectRoot = $real !== false ? $real : $projectRoot;
    }

    /**
     * Records that a test file depends on the given source file.
     */
    public function link(string $testFile, string $sourceFile): void
    {
        $testRel = $this->relative($testFile);
        $sourceRel = $this->relative($sourceFile);

        if ($sourceRel === null || $testRel === null) {
            return;
        }

        if (! isset($this->fileIds[$sourceRel])) {
            $id = count($this->files);
            $this->files[$id] = $sourceRel;
            $this->fileIds[$sourceRel] = $id;
        }

        $this->edges[$testRel][] = $this->fileIds[$sourceRel];
    }

    /**
     * Returns the set of test files whose dependencies intersect $changedFiles.
     *
     * Two resolution paths:
     *   1. **Coverage edges** — test depends on a PHP source file that changed.
     *   2. **Watch patterns** — a non-PHP file (JS, CSS, config, …) matches a
     *      glob that maps to a test directory; every test under that directory
     *      is affected.
     *
     * @param  array<int, string>  $changedFiles  Absolute or relative paths.
     * @return array<int, string> Relative test file paths.
     */
    public function affected(array $changedFiles): array
    {
        // 1. Coverage-edge lookup (PHP → PHP).
        $changedIds = [];

        foreach ($changedFiles as $file) {
            $rel = $this->relative($file);

            if ($rel === null) {
                continue;
            }

            if (isset($this->fileIds[$rel])) {
                $changedIds[$this->fileIds[$rel]] = true;
            }
        }

        $affectedSet = [];

        foreach ($this->edges as $testFile => $ids) {
            foreach ($ids as $id) {
                if (isset($changedIds[$id])) {
                    $affectedSet[$testFile] = true;

                    break;
                }
            }
        }

        // 2. Watch-pattern lookup (non-PHP assets → test directories).
        $watchPatterns = WatchPatterns::instance();
        $normalised = [];

        foreach ($changedFiles as $file) {
            $rel = $this->relative($file);

            if ($rel !== null) {
                $normalised[] = $rel;
            }
        }

        $dirs = $watchPatterns->matchedDirectories($this->projectRoot, $normalised);
        $allTestFiles = array_keys($this->edges);

        foreach ($watchPatterns->testsUnderDirectories($dirs, $allTestFiles) as $testFile) {
            $affectedSet[$testFile] = true;
        }

        return array_keys($affectedSet);
    }

    /**
     * Returns `true` if the given test file has any recorded dependencies.
     */
    public function knowsTest(string $testFile): bool
    {
        $rel = $this->relative($testFile);

        return $rel !== null && isset($this->edges[$rel]);
    }

    /**
     * @return array<int, string>  All project-relative test files the graph knows.
     */
    public function allTestFiles(): array
    {
        return array_keys($this->edges);
    }

    public function setFingerprint(array $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    public function fingerprint(): array
    {
        return $this->fingerprint;
    }

    public function setRecordedAtSha(?string $sha): void
    {
        $this->recordedAtSha = $sha;
    }

    public function recordedAtSha(): ?string
    {
        return $this->recordedAtSha;
    }

    /**
     * Replaces edges for the given test files. Used during a partial record
     * run so that existing edges for other tests are preserved.
     *
     * @param  array<string, array<int, string>>  $testToFiles
     */
    public function replaceEdges(array $testToFiles): void
    {
        foreach ($testToFiles as $testFile => $sources) {
            $testRel = $this->relative($testFile);

            if ($testRel === null) {
                continue;
            }

            $this->edges[$testRel] = [];

            foreach ($sources as $source) {
                $this->link($testFile, $source);
            }

            // Deduplicate ids for this test.
            $this->edges[$testRel] = array_values(array_unique($this->edges[$testRel]));
        }
    }

    /**
     * Drops edges whose test file no longer exists on disk. Prevents the graph
     * from keeping stale entries for deleted / renamed tests that would later
     * be flagged as affected and confuse PHPUnit's discovery.
     */
    public function pruneMissingTests(): void
    {
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        foreach (array_keys($this->edges) as $testRel) {
            if (! is_file($root.$testRel)) {
                unset($this->edges[$testRel]);
            }
        }
    }

    public static function load(string $projectRoot, string $path): ?self
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ($data['schema'] ?? null) !== 1) {
            return null;
        }

        $graph = new self($projectRoot);
        $graph->fingerprint = is_array($data['fingerprint'] ?? null) ? $data['fingerprint'] : [];
        $graph->recordedAtSha = is_string($data['recorded_at_sha'] ?? null) ? $data['recorded_at_sha'] : null;
        $graph->files = is_array($data['files'] ?? null) ? array_values($data['files']) : [];
        $graph->fileIds = array_flip($graph->files);
        $graph->edges = is_array($data['edges'] ?? null) ? $data['edges'] : [];

        return $graph;
    }

    public function save(string $path): bool
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        $payload = [
            'schema' => 1,
            'fingerprint' => $this->fingerprint,
            'recorded_at_sha' => $this->recordedAtSha,
            'files' => $this->files,
            'edges' => $this->edges,
        ];

        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        if (@file_put_contents($tmp, $json) === false) {
            return false;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    /**
     * Normalises a path to be relative to the project root; returns `null` for
     * paths we should ignore (outside the project, unknown, virtual, vendor).
     *
     * Accepts both absolute paths (from Xdebug/PCOV coverage) and
     * project-relative paths (from `git diff`) — we normalise without relying
     * on `realpath()` of relative paths because the current working directory
     * is not guaranteed to be the project root.
     */
    private function relative(string $path): ?string
    {
        if ($path === '' || $path === 'unknown') {
            return null;
        }

        if (str_contains($path, "eval()'d")) {
            return null;
        }

        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        $isAbsolute = str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) >= 2 && $path[1] === ':'); // Windows drive

        if ($isAbsolute) {
            $real = @realpath($path);

            if ($real === false) {
                $real = $path;
            }

            if (! str_starts_with($real, $root)) {
                return null;
            }

            // Always normalise to forward slashes. Windows' native separator
            // would otherwise produce keys that never match paths reported
            // by `git` (which always uses forward slashes).
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root)));
        } else {
            // Normalise directory separators and strip any "./" prefix.
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $path);

            while (str_starts_with($relative, './')) {
                $relative = substr($relative, 2);
            }
        }

        // Vendor packages are pinned by composer.lock. Any upgrade bumps the
        // fingerprint and invalidates the graph wholesale, so there is no
        // reason to track individual vendor files — doing so inflates the
        // graph by orders of magnitude on Laravel-style projects.
        if (str_starts_with($relative, 'vendor/')) {
            return null;
        }

        return $relative;
    }
}
