<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Support\Container;
use PHPUnit\Framework\TestStatus\TestStatus;

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
     * Table edges: test file (relative) → list of lowercase SQL table
     * names the test queried during record. Populated from the
     * Recorder's `perTestTables()` snapshot; consumed at replay time
     * to do surgical invalidation when a migration changes — the
     * test only re-runs if its set intersects the tables the changed
     * migration touches. Empty for tests that never hit the DB, which
     * is exactly why those tests stay unaffected by migration edits.
     *
     * Unlike `$edges`, we store names rather than ids: the table
     * universe is small (hundreds at most on a giant app), storing
     * strings keeps the on-disk graph diff-readable, and the lookup
     * cost is negligible compared to the per-file ids used above.
     *
     * @var array<string, array<int, string>>
     */
    private array $testTables = [];

    /**
     * Environment fingerprint captured at record time.
     *
     * @var array<string, mixed>
     */
    private array $fingerprint = [];

    /**
     * Per-branch baselines. Each branch independently tracks:
     *   - `sha`     — last HEAD at which `--tia` ran on this branch
     *   - `tree`    — content hashes of modified files at that point
     *   - `results` — per-test status + message + time
     *
     * Graph edges (test → source) stay shared across branches because
     * structure doesn't change per branch. Only run-state is per-branch so
     * a failing test on one branch doesn't poison another branch's replay.
     *
     * @var array<string, array{
     *     sha: ?string,
     *     tree: array<string, string>,
     *     results: array<string, array{status: int, message: string, time: float, assertions?: int}>
     * }>
     */
    private array $baselines = [];

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
        // Normalise all changed paths once.
        $normalised = [];

        foreach ($changedFiles as $file) {
            $rel = $this->relative($file);

            if ($rel !== null) {
                $normalised[] = $rel;
            }
        }

        $affectedSet = [];

        // Migration changes don't flow through the coverage-edge path —
        // `RefreshDatabase` in every test's `setUp()` means every test
        // has an edge to every migration, so step 1 would re-run the
        // whole DB-touching suite on any migration edit. Route them
        // separately: static-parse the migration source, union the
        // referenced tables, and match tests whose recorded query
        // footprint intersects that set. Missed files (rare: migrations
        // with pure raw SQL or dynamic names) fall back to the watch
        // pattern below.
        $migrationPaths = [];
        $nonMigrationPaths = [];

        foreach ($normalised as $rel) {
            if ($this->isMigrationPath($rel)) {
                $migrationPaths[] = $rel;
            } else {
                $nonMigrationPaths[] = $rel;
            }
        }

        $changedTables = [];
        $unparseableMigrations = [];

        foreach ($migrationPaths as $rel) {
            $tables = $this->tablesForMigration($rel);

            if ($tables === []) {
                $unparseableMigrations[] = $rel;

                continue;
            }

            foreach ($tables as $table) {
                $changedTables[$table] = true;
            }
        }

        if ($changedTables !== []) {
            foreach ($this->testTables as $testFile => $tables) {
                if (isset($affectedSet[$testFile])) {
                    continue;
                }

                foreach ($tables as $table) {
                    if (isset($changedTables[$table])) {
                        $affectedSet[$testFile] = true;

                        break;
                    }
                }
            }
        }

        // 1. Coverage-edge lookup (PHP → PHP). Migrations are already
        // handled above; skipping them here prevents their always-on
        // coverage edges from invalidating the whole DB suite.
        $changedIds = [];
        $unknownSourceDirs = [];

        foreach ($nonMigrationPaths as $rel) {
            if (isset($this->fileIds[$rel])) {
                $changedIds[$this->fileIds[$rel]] = true;
            } elseif (str_ends_with($rel, '.php') && ! str_starts_with($rel, 'tests/')) {
                // Source PHP file unknown to the graph — might be a new file
                // that only exists on this branch (graph inherited from main).
                // Track its directory for the sibling heuristic (step 3).
                $unknownSourceDirs[dirname($rel)] = true;
            }
        }

        foreach ($this->edges as $testFile => $ids) {
            if (isset($affectedSet[$testFile])) {
                continue;
            }

            foreach ($ids as $id) {
                if (isset($changedIds[$id])) {
                    $affectedSet[$testFile] = true;

                    break;
                }
            }
        }

        // 2. Watch-pattern lookup — fallback for files we don't have
        // precise edges for. When a file is already in `$fileIds` step
        // 1 resolved it surgically; broadcasting it again through the
        // watch pattern would re-add every test the pattern maps to,
        // defeating the point of recording the edge in the first place.
        // Blade templates captured via Laravel's view composer are the
        // motivating case — we want their specific tests, not every
        // feature test. Migrations whose static parse yielded nothing
        // (exotic syntax, raw SQL) are funneled back in here too so
        // broad invalidation still kicks in for edge cases we can't
        // parse.
        $unknownToGraph = $unparseableMigrations;
        foreach ($nonMigrationPaths as $rel) {
            if (! isset($this->fileIds[$rel])) {
                $unknownToGraph[] = $rel;
            }
        }

        /** @var WatchPatterns $watchPatterns */
        $watchPatterns = Container::getInstance()->get(WatchPatterns::class);

        $dirs = $watchPatterns->matchedDirectories($this->projectRoot, $unknownToGraph);
        $allTestFiles = array_keys($this->edges);

        foreach ($watchPatterns->testsUnderDirectories($dirs, $allTestFiles) as $testFile) {
            $affectedSet[$testFile] = true;
        }

        // 3. Sibling heuristic for unknown source files.
        //
        // When a PHP source file is unknown to the graph (no test depends on
        // it), it is either genuinely untested OR it was added on a branch
        // whose graph was inherited from another branch (e.g. main). In the
        // latter case the graph simply never saw the file.
        //
        // To avoid silent misses: find tests that already cover ANY file in
        // the same directory. If `app/Models/OrderItem.php` is unknown but
        // `app/Models/Order.php` is covered by `OrderTest`, run `OrderTest`
        // — it likely exercises sibling files in the same module.
        //
        // This over-runs slightly (sibling may be unrelated) but never
        // under-runs. And once the test executes, its coverage captures the
        // new file → graph self-heals for next run.
        if ($unknownSourceDirs !== []) {
            foreach ($this->edges as $testFile => $ids) {
                if (isset($affectedSet[$testFile])) {
                    continue;
                }

                foreach ($ids as $id) {
                    if (! isset($this->files[$id])) {
                        continue;
                    }

                    $depDir = dirname($this->files[$id]);

                    if (isset($unknownSourceDirs[$depDir])) {
                        $affectedSet[$testFile] = true;

                        break;
                    }
                }
            }
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
     * @return array<int, string> All project-relative test files the graph knows.
     */
    public function allTestFiles(): array
    {
        return array_keys($this->edges);
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     */
    public function setFingerprint(array $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }

    /**
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        return $this->fingerprint;
    }

    /**
     * Returns the SHA the given branch last ran against, or falls back to
     * `$fallbackBranch` (typically `main`) when this branch has no baseline
     * yet. That way a freshly-created feature branch inherits main's
     * baseline on its first run.
     */
    public function recordedAtSha(string $branch, string $fallbackBranch = 'main'): ?string
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        return $baseline['sha'];
    }

    public function setRecordedAtSha(string $branch, ?string $sha): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['sha'] = $sha;
    }

    public function setResult(string $branch, string $testId, int $status, string $message, float $time, int $assertions = 0): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['results'][$testId] = [
            'status' => $status,
            'message' => $message,
            'time' => $time,
            'assertions' => $assertions,
        ];
    }

    /**
     * Returns the cached assertion count for a test, or `null` if unknown.
     * Callers use this to feed `addToAssertionCount()` at replay time so
     * the "Tests: N passed (M assertions)" banner matches the recorded run
     * instead of defaulting to 1 assertion per test.
     */
    public function getAssertions(string $branch, string $testId, string $fallbackBranch = 'main'): ?int
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        if (! isset($baseline['results'][$testId]['assertions'])) {
            return null;
        }

        return $baseline['results'][$testId]['assertions'];
    }

    public function getResult(string $branch, string $testId, string $fallbackBranch = 'main'): ?TestStatus
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        if (! isset($baseline['results'][$testId])) {
            return null;
        }

        $r = $baseline['results'][$testId];

        // PHPUnit's `TestStatus::from(int)` ignores messages, so reconstruct
        // each variant via its specific factory. Keeps the stored message
        // intact (important for skips/failures shown to the user).
        return match ($r['status']) {
            0 => TestStatus::success(),
            1 => TestStatus::skipped($r['message']),
            2 => TestStatus::incomplete($r['message']),
            3 => TestStatus::notice($r['message']),
            4 => TestStatus::deprecation($r['message']),
            5 => TestStatus::risky($r['message']),
            6 => TestStatus::warning($r['message']),
            7 => TestStatus::failure($r['message']),
            8 => TestStatus::error($r['message']),
            default => TestStatus::unknown(),
        };
    }

    /**
     * @param  array<string, string>  $tree  project-relative path → content hash
     */
    public function setLastRunTree(string $branch, array $tree): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['tree'] = $tree;
    }

    /**
     * Wipes cached per-test results for the given branch. Edges and tree
     * snapshot stay intact — the graph still describes the code correctly,
     * only the "what happened last time" data is reset. Used on
     * environmental fingerprint drift: the edges were recorded elsewhere
     * (e.g. CI) so they're still valid, but the results aren't trustworthy
     * on this machine until the tests re-run here.
     */
    public function clearResults(string $branch): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['results'] = [];
    }

    /**
     * @return array<string, string>
     */
    public function lastRunTree(string $branch, string $fallbackBranch = 'main'): array
    {
        return $this->baselineFor($branch, $fallbackBranch)['tree'];
    }

    /**
     * @return array{sha: ?string, tree: array<string, string>, results: array<string, array{status: int, message: string, time: float, assertions?: int}>}
     */
    private function baselineFor(string $branch, string $fallbackBranch): array
    {
        if (isset($this->baselines[$branch])) {
            return $this->baselines[$branch];
        }

        if ($branch !== $fallbackBranch && isset($this->baselines[$fallbackBranch])) {
            return $this->baselines[$fallbackBranch];
        }

        return ['sha' => null, 'tree' => [], 'results' => []];
    }

    private function ensureBaseline(string $branch): void
    {
        if (! isset($this->baselines[$branch])) {
            $this->baselines[$branch] = ['sha' => null, 'tree' => [], 'results' => []];
        }
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
     * Replaces table edges for the given test files. Table names are
     * lowercased + deduplicated; the input comes straight from the
     * Recorder's `perTestTables()` snapshot. Tests absent from the
     * input keep their existing table set (same partial-update policy
     * as `replaceEdges`).
     *
     * @param  array<string, array<int, string>>  $testToTables
     */
    public function replaceTestTables(array $testToTables): void
    {
        foreach ($testToTables as $testFile => $tables) {
            $testRel = $this->relative($testFile);

            if ($testRel === null) {
                continue;
            }

            $normalised = [];

            foreach ($tables as $table) {
                $lower = strtolower($table);

                if ($lower !== '') {
                    $normalised[$lower] = true;
                }
            }

            $names = array_keys($normalised);
            sort($names);

            $this->testTables[$testRel] = $names;
        }
    }

    /**
     * Projects under Laravel conventionally keep migrations at
     * `database/migrations/`. We recognise the directory as a prefix
     * so nested subdirectories (a pattern some teams use for grouping
     * — `database/migrations/tenant/`, `database/migrations/archived/`)
     * are still routed through the table-intersection path.
     */
    private function isMigrationPath(string $rel): bool
    {
        return str_starts_with($rel, 'database/migrations/') && str_ends_with($rel, '.php');
    }

    /**
     * Reads `$rel` relative to the project root and extracts the
     * tables it declares via `Schema::create/table/drop/rename`.
     * Empty on missing/unreadable files or when the parser finds
     * nothing — the caller escalates those cases to the watch
     * pattern safety net.
     *
     * @return list<string>
     */
    private function tablesForMigration(string $rel): array
    {
        $absolute = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$rel;

        if (! is_file($absolute)) {
            return [];
        }

        $content = @file_get_contents($absolute);

        if ($content === false) {
            return [];
        }

        return TableExtractor::fromMigrationSource($content);
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

        foreach (array_keys($this->testTables) as $testRel) {
            if (! is_file($root.$testRel)) {
                unset($this->testTables[$testRel]);
            }
        }
    }

    /**
     * Rebuilds a graph from its JSON representation. Returns `null` when
     * the payload is missing, unreadable, or schema-incompatible. Separated
     * from transport (state backend, file, etc.) so tests can feed bytes
     * directly without touching disk.
     */
    public static function decode(string $json, string $projectRoot): ?self
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ($data['schema'] ?? null) !== 1) {
            return null;
        }

        $graph = new self($projectRoot);
        $graph->fingerprint = is_array($data['fingerprint'] ?? null) ? $data['fingerprint'] : [];
        $graph->files = is_array($data['files'] ?? null) ? array_values($data['files']) : [];
        $graph->fileIds = array_flip($graph->files);
        $graph->edges = is_array($data['edges'] ?? null) ? $data['edges'] : [];
        $graph->baselines = is_array($data['baselines'] ?? null) ? $data['baselines'] : [];

        if (isset($data['test_tables']) && is_array($data['test_tables'])) {
            foreach ($data['test_tables'] as $testRel => $tables) {
                if (! is_string($testRel)) {
                    continue;
                }
                if (! is_array($tables)) {
                    continue;
                }
                $names = [];

                foreach ($tables as $table) {
                    if (is_string($table) && $table !== '') {
                        $names[] = $table;
                    }
                }

                if ($names !== []) {
                    $graph->testTables[$testRel] = $names;
                }
            }
        }

        return $graph;
    }

    /**
     * Serialises the graph to its JSON on-disk form. Returns `null` if the
     * payload can't be encoded (extremely rare — pathological UTF-8 only).
     * Persistence is the caller's responsibility: write the returned bytes
     * through whatever `State` implementation is in play.
     */
    public function encode(): ?string
    {
        $payload = [
            'schema' => 1,
            'fingerprint' => $this->fingerprint,
            'files' => $this->files,
            'edges' => $this->edges,
            'baselines' => $this->baselines,
            'test_tables' => $this->testTables,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $json;
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
