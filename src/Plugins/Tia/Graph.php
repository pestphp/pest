<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Support\Container;
use PHPUnit\Framework\TestStatus\TestStatus;
use Symfony\Component\Console\Output\OutputInterface;

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
     * Inertia page component edges: test file (relative) → list of
     * component names the test server-side rendered (whatever was
     * passed to `Inertia::render($component, …)`). Populated from
     * `Recorder::perTestInertiaComponents()`; consumed at replay time
     * so an edit to `resources/js/Pages/Users/Show.vue` only invalidates
     * tests that rendered `Users/Show`. Same string-keyed shape as
     * `$testTables` for the same diff-readable reasons.
     *
     * @var array<string, array<int, string>>
     */
    private array $testInertiaComponents = [];

    /**
     * Inverted JS dependency map: project-relative source path under
     * `resources/js/**` → list of Inertia page components that
     * transitively import it. Populated at record time by
     * `JsModuleGraph::build()` (Vite module graph via Node helper,
     * with a PHP fallback). Replay uses this to route a
     * `Components/Button.vue` edit directly to the pages that depend
     * on it, intersecting against `$testInertiaComponents` for
     * surgical invalidation.
     *
     * @var array<string, array<int, string>>
     */
    private array $jsFileToComponents = [];

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

        // Inertia page-component routing. When a page under
        // `resources/js/Pages/` changes, map it to the component name
        // Inertia would use (the path relative to `Pages/`, extension
        // stripped) and intersect with the captured component edges.
        // Only invalidates tests that actually rendered the page.
        // Pages with no captured edges (never rendered during record,
        // brand-new on this branch) fall through to the watch-pattern
        // fallback — safe over-run. Pages handled here are tracked in
        // `$preciselyHandledPages` so the watch broadcast and JS-dep
        // lookup don't re-route them.
        $changedComponents = [];
        $preciselyHandledPages = [];

        foreach ($nonMigrationPaths as $rel) {
            $component = $this->componentForInertiaPage($rel);

            if ($component === null) {
                continue;
            }

            if ($this->anyTestUses($this->testInertiaComponents, $component)) {
                $changedComponents[$component] = true;
                $preciselyHandledPages[$rel] = true;
            }
        }

        // Shared JS files (Components, Layouts, composables, etc.)
        // aren't Inertia pages but pages depend on them transitively.
        // `$jsFileToComponents` was computed at record time by walking
        // Vite's module graph, so a change to
        // `resources/js/Components/Button.vue` resolves directly to
        // the set of page components that import it. Union those into
        // `$changedComponents`. Files that aren't in the JS dep map
        // fall through to the watch pattern below — same safety-net
        // path the Inertia block above uses for unresolved pages.
        $sharedFilesResolved = [];
        foreach ($nonMigrationPaths as $rel) {
            if (isset($preciselyHandledPages[$rel])) {
                continue;
            }

            if (! isset($this->jsFileToComponents[$rel])) {
                continue;
            }

            $touchedAny = false;
            foreach ($this->jsFileToComponents[$rel] as $pageComponent) {
                if ($this->anyTestUses($this->testInertiaComponents, $pageComponent)) {
                    $changedComponents[$pageComponent] = true;
                    $touchedAny = true;
                }
            }

            if ($touchedAny) {
                $sharedFilesResolved[$rel] = true;
            }
        }

        // Orphan detection for NEW JS files. `$jsFileToComponents` is
        // a record-time snapshot; files added since (a fresh Vue
        // component, a new shared util, etc.) are absent from it.
        // Today the broad watch pattern catches them — correct but
        // pessimistic: a JS file that literally no page imports
        // would still invalidate the entire browser dir.
        //
        // Fix: for each new JS file in the changed set, ask Vite
        // (strict mode — no PHP fallback) which pages transitively
        // import it. If none → orphan, suppress the broadcast. If
        // some → precise union with their tests' components. The
        // Node helper is the only resolver trustworthy enough to
        // honour a *negative* answer (the PHP parser can silently
        // miss custom aliases). When Node is unreachable we leave
        // the files alone and let the watch pattern do its job.
        $newJsFiles = [];
        foreach ($nonMigrationPaths as $rel) {
            if (isset($preciselyHandledPages[$rel])) {
                continue;
            }
            if (isset($sharedFilesResolved[$rel])) {
                continue;
            }
            if (isset($this->jsFileToComponents[$rel])) {
                continue;
            }
            if (! str_starts_with($rel, 'resources/js/')) {
                continue;
            }
            $newJsFiles[] = $rel;
        }

        if ($newJsFiles !== []) {
            $freshMap = JsModuleGraph::buildStrict($this->projectRoot);

            if ($freshMap === null) {
                // Vite resolver was unavailable (Node missing, cold-start
                // timeout, vite.config refused to load). Falling back to
                // the broad watch pattern is the correct call, but
                // doing so silently can make a slow replay feel
                // inexplicable — surface a single line so the user
                // knows precision was downgraded for these files.
                $output = Container::getInstance()->get(OutputInterface::class);
                if ($output instanceof OutputInterface) {
                    $output->writeln(sprintf(
                        '  <fg=yellow>TIA</> Vite resolver unavailable — falling back to watch pattern for %d new JS file(s).',
                        count($newJsFiles),
                    ));
                }
            } else {
                foreach ($newJsFiles as $rel) {
                    $pages = $freshMap[$rel] ?? [];

                    if ($pages === []) {
                        // Vite itself says nothing imports this file.
                        // Safe to skip — mark handled so the watch
                        // pattern below doesn't re-broadcast it.
                        $sharedFilesResolved[$rel] = true;

                        continue;
                    }

                    $touchedAny = false;
                    foreach ($pages as $pageComponent) {
                        if ($this->anyTestUses($this->testInertiaComponents, $pageComponent)) {
                            $changedComponents[$pageComponent] = true;
                            $touchedAny = true;
                        }
                    }

                    if ($touchedAny) {
                        $sharedFilesResolved[$rel] = true;
                    }
                }
            }
        }

        if ($changedComponents !== []) {
            foreach ($this->testInertiaComponents as $testFile => $components) {
                if (isset($affectedSet[$testFile])) {
                    continue;
                }

                foreach ($components as $component) {
                    if (isset($changedComponents[$component])) {
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

                continue;
            }

            if (str_ends_with($rel, '.php') && ! str_starts_with($rel, 'tests/')) {
                $absolute = $this->projectRoot.'/'.$rel;

                if (! is_file($absolute)) {
                    // Deleted source file unknown to the graph — can't affect
                    // any test because no edge ever pointed to it.
                    continue;
                }

                // Source PHP file unknown to the graph — might be a new file
                // that only exists on this branch (graph inherited from main).
                // Only use the sibling heuristic for files that commonly
                // participate in framework discovery / bootstrap. Ordinary new
                // classes, enums, DTOs, services, etc. should not re-run sibling
                // tests just because they live in the same directory.
                if ($this->usesSiblingHeuristicForUnknownPhp($rel)) {
                    $unknownSourceDirs[dirname($rel)] = true;
                }
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
        // Exclude paths that were already routed precisely through
        // either the Inertia page-component path or the shared-JS
        // dependency path. Broadcasting them again via the watch
        // pattern would re-add every test the pattern maps to,
        // defeating the surgical match.
        $unknownToGraph = $unparseableMigrations;
        foreach ($nonMigrationPaths as $rel) {
            if (isset($preciselyHandledPages[$rel])) {
                continue;
            }
            if (isset($sharedFilesResolved[$rel])) {
                continue;
            }
            if (! isset($this->fileIds[$rel])) {
                if (! is_file($this->projectRoot.'/'.$rel)) {
                    // Deleted file unknown to the graph — no edge ever
                    // pointed to it, so it can't affect any test.
                    continue;
                }

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
        // To avoid silent misses for framework-discovered files: find tests
        // that already cover ANY file in the same directory. If
        // `app/Listeners/SendWelcomeEmail.php` is unknown but neighbouring
        // listeners are covered by a mail-flow test, run that test — it likely
        // exercises the same discovery surface.
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
     * Replaces Inertia component edges for the given test files. Names
     * preserve case (they're identifiers like `Users/Show`, not
     * user-supplied strings) but duplicates are collapsed. Same
     * partial-update policy as `replaceTestTables`.
     *
     * @param  array<string, array<int, string>>  $testToComponents
     */
    public function replaceTestInertiaComponents(array $testToComponents): void
    {
        foreach ($testToComponents as $testFile => $components) {
            $testRel = $this->relative($testFile);

            if ($testRel === null) {
                continue;
            }

            $normalised = [];

            foreach ($components as $component) {
                if ($component !== '') {
                    $normalised[$component] = true;
                }
            }

            $names = array_keys($normalised);
            sort($names);

            $this->testInertiaComponents[$testRel] = $names;
        }
    }

    /**
     * Replaces the whole JS dep map. Called at record time with the
     * output of `JsModuleGraph::build()`. Empty input is treated as a
     * resolver failure (Node missing, Vite refused to load, transient
     * `npm install`) rather than a legitimate "no JS pages" signal —
     * we keep the previous map. Stale entries for genuinely-deleted
     * pages are harmless because deleted files never enter the
     * changed set; over-broadcasting every JS edit through the watch
     * pattern after a flaky Node run would be a real regression.
     *
     * @param  array<string, array<int, string>>  $fileToComponents
     */
    public function replaceJsFileToComponents(array $fileToComponents): void
    {
        $out = [];

        foreach ($fileToComponents as $path => $components) {
            if ($path === '') {
                continue;
            }
            $names = [];

            foreach ($components as $component) {
                if ($component !== '') {
                    $names[$component] = true;
                }
            }

            if ($names === []) {
                continue;
            }

            $keys = array_keys($names);
            sort($keys);
            $out[$path] = $keys;
        }

        if ($out === []) {
            return;
        }

        ksort($out);

        $this->jsFileToComponents = $out;
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
     * Unknown PHP files have no historical edge yet. Keep sibling fan-out only
     * for framework-discovered / boot-loaded conventions where adding a file can
     * change behaviour without another source file changing too.
     */
    private function usesSiblingHeuristicForUnknownPhp(string $rel): bool
    {
        static $prefixes = [
            'app/Providers/',
            'app/Listeners/',
            'app/Events/',
            'app/Observers/',
            'app/Policies/',
            'app/Console/Commands/',
            'app/Mail/',
            'app/Notifications/',
            'database/factories/',
            'database/seeders/',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                return true;
            }
        }

        return false;
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
     * Maps a project-relative path to its Inertia component name if it
     * lives under the project's pages directory with a recognised
     * framework extension. Returns null otherwise so callers can
     * cheaply ignore non-page files. Matches Inertia's resolver
     * convention: strip the pages prefix, strip the extension, preserve
     * the remaining slashes (`Users/Show.vue` → `Users/Show`).
     *
     * Both `resources/js/Pages/` (the classic Inertia-Vue convention)
     * and `resources/js/pages/` (the Laravel React starter kit, and
     * other lowercase-by-default setups) are accepted — paths from
     * git are case-sensitive on Linux, so we must match the exact
     * casing used by the project rather than picking one and forcing
     * the other to fall through to the broad watch pattern.
     */
    private function componentForInertiaPage(string $rel): ?string
    {
        foreach (['resources/js/Pages/', 'resources/js/pages/'] as $prefix) {
            if (! str_starts_with($rel, $prefix)) {
                continue;
            }

            $tail = substr($rel, strlen($prefix));
            $dot = strrpos($tail, '.');

            if ($dot === false) {
                return null;
            }

            $extension = substr($tail, $dot + 1);

            if (! in_array($extension, ['vue', 'tsx', 'jsx', 'svelte', 'ts', 'js'], true)) {
                return null;
            }

            $name = substr($tail, 0, $dot);

            return $name === '' ? null : $name;
        }

        return null;
    }

    /**
     * Whether any test's component set contains `$component`. Used to
     * decide between precise edge matching and watch-pattern fallback
     * for a changed Inertia page file.
     *
     * @param  array<string, array<int, string>>  $edges
     */
    private function anyTestUses(array $edges, string $component): bool
    {
        foreach ($edges as $components) {
            if (in_array($component, $components, true)) {
                return true;
            }
        }

        return false;
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

        foreach (array_keys($this->testInertiaComponents) as $testRel) {
            if (! is_file($root.$testRel)) {
                unset($this->testInertiaComponents[$testRel]);
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

        if (isset($data['test_inertia_components']) && is_array($data['test_inertia_components'])) {
            foreach ($data['test_inertia_components'] as $testRel => $components) {
                if (! is_string($testRel)) {
                    continue;
                }
                if (! is_array($components)) {
                    continue;
                }
                $names = [];

                foreach ($components as $component) {
                    if (is_string($component) && $component !== '') {
                        $names[] = $component;
                    }
                }

                if ($names !== []) {
                    $graph->testInertiaComponents[$testRel] = $names;
                }
            }
        }

        if (isset($data['js_file_to_components']) && is_array($data['js_file_to_components'])) {
            foreach ($data['js_file_to_components'] as $path => $components) {
                if (! is_string($path)) {
                    continue;
                }
                if ($path === '') {
                    continue;
                }
                if (! is_array($components)) {
                    continue;
                }
                $names = [];

                foreach ($components as $component) {
                    if (is_string($component) && $component !== '') {
                        $names[] = $component;
                    }
                }

                if ($names !== []) {
                    $graph->jsFileToComponents[$path] = $names;
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
            'test_inertia_components' => $this->testInertiaComponents,
            'js_file_to_components' => $this->jsFileToComponents,
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
