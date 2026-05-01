<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Factories\TestCaseFactory;
use Pest\Support\Container;
use Pest\Support\View;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestStatus\TestStatus;

/**
 * @internal
 */
final class Graph
{
    /** @var array<int, string> */
    private array $files = [];

    /** @var array<string, int> */
    private array $fileIds = [];

    /** @var array<string, array<int, int>> */
    private array $edges = [];

    /** @var array<string, array<int, string>> */
    private array $testTables = [];

    /** @var array<string, array<int, string>> */
    private array $testInertiaComponents = [];

    /** @var array<string, array<int, string>> */
    private array $jsFileToComponents = [];

    /** @var array<string, mixed> */
    private array $fingerprint = [];

    /**
     * @var array<string, array{
     *     sha: ?string,
     *     tree: array<string, string>,
     *     results: array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>
     * }>
     */
    private array $baselines = [];

    // Resolved via realpath() so coverage driver paths (always real targets) match even when CWD is a symlink.
    private readonly string $projectRoot;

    /** @var array<string, true>|null */
    private ?array $archTestFiles = null;

    public function __construct(string $projectRoot)
    {
        $real = @realpath($projectRoot);

        $this->projectRoot = $real !== false ? $real : $projectRoot;
    }

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
     * @param  array<int, string>  $changedFiles  Absolute or relative paths.
     * @return array<int, string>
     */
    public function affected(array $changedFiles): array
    {
        $normalised = [];

        foreach ($changedFiles as $file) {
            $rel = $this->relative($file);

            if ($rel !== null) {
                $normalised[] = $rel;
            }
        }

        $affectedSet = [];

        // Migrations can't flow through coverage edges: `RefreshDatabase` gives every test an edge to
        // every migration, so any migration change would re-run the whole DB suite. Route them via
        // table-intersection instead; unparseable migrations fall through to the watch pattern.
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

        // Inertia page routing: map changed page files to component names and intersect with recorded
        // component edges. Pages with no captured edges fall through to the watch pattern.
        $globalFrontendRuntimeFiles = [];

        foreach ($nonMigrationPaths as $rel) {
            if (! $this->isGlobalFrontendRuntimePath($rel)) {
                continue;
            }

            foreach (array_keys($this->testInertiaComponents) as $testFile) {
                $affectedSet[$testFile] = true;
            }

            $globalFrontendRuntimeFiles[$rel] = true;
        }

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

        // Shared JS files: resolve via the recorded Vite module graph to their dependent page components.
        // Files absent from the map fall through to the watch pattern.
        $sharedFilesResolved = [];
        foreach ($nonMigrationPaths as $rel) {
            if (isset($globalFrontendRuntimeFiles[$rel])) {
                continue;
            }
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

        // New JS files absent from the record-time map: ask Vite (strict, no PHP fallback) which pages
        // import them. A negative answer suppresses the broad watch broadcast; Node is the only resolver
        // trustworthy enough to honour a negative (PHP parser can miss custom aliases).
        $newJsFiles = [];
        foreach ($nonMigrationPaths as $rel) {
            if (isset($globalFrontendRuntimeFiles[$rel])) {
                continue;
            }
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
                // Vite resolver unavailable — falling back to watch pattern; surface a line so the user
                // knows precision was downgraded rather than leaving the slower replay unexplained.
                View::render('components.badge', [
                    'type' => 'WARN',
                    'content' => sprintf(
                        'TIA Vite resolver unavailable — falling back to watch pattern for %d new JS file(s).',
                        count($newJsFiles),
                    ),
                ]);
            } else {
                foreach ($newJsFiles as $rel) {
                    $pages = $freshMap[$rel] ?? [];

                    if ($pages === []) {
                        // Vite confirms no page imports this file — suppress the watch broadcast.
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

        // Coverage-edge lookup (PHP → PHP). Migrations already handled above; skipping here prevents
        // their always-on edges from re-running the whole DB suite.
        $changedIds = [];
        $unknownSourceDirs = [];
        $sourcePhpChanged = false;

        foreach ($nonMigrationPaths as $rel) {
            if ($this->isProjectSourcePhp($rel)) {
                $sourcePhpChanged = true;
            }

            if (isset($this->fileIds[$rel])) {
                $changedIds[$this->fileIds[$rel]] = true;

                continue;
            }

            if (str_ends_with($rel, '.php') && ! str_starts_with($rel, 'tests/')) {
                $absolute = $this->projectRoot.'/'.$rel;

                if (! is_file($absolute)) {
                    // Deleted source file unknown to the graph — no edge ever pointed to it.
                    continue;
                }

                if ($this->usesSiblingHeuristicForUnknownPhp($rel)) {
                    $unknownSourceDirs[dirname($rel)] = true;
                }
            }
        }

        // Arch tests inspect structure by namespace/path, never producing coverage edges for the files
        // they examine — so a new class can fail an arch expectation without any edge to it.
        if ($sourcePhpChanged) {
            foreach (array_keys($this->edges) as $testFile) {
                if ($this->isArchTestFile($testFile)) {
                    $affectedSet[$testFile] = true;
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

        // Unknown Blade files: walk static references (@include, @extends, <x-*>) up to rendered
        // ancestors and invalidate only tests that covered them.
        $staticallyHandledBlade = [];
        foreach ($nonMigrationPaths as $rel) {
            if (isset($this->fileIds[$rel])) {
                continue;
            }
            if (! $this->isBladePath($rel)) {
                continue;
            }
            if (! is_file($this->projectRoot.'/'.$rel)) {
                continue;
            }

            $bladeAffected = $this->affectedByStaticBladeUsage($rel);

            if ($bladeAffected !== []) {
                foreach ($bladeAffected as $testFile) {
                    $affectedSet[$testFile] = true;
                }

                $staticallyHandledBlade[$rel] = true;
            } elseif ($this->isBladeComponentPath($rel)) {
                // Anonymous component with no static usages — treat as orphan rather than broadcasting.
                $staticallyHandledBlade[$rel] = true;
            }
        }

        // Watch-pattern fallback: files with no precise edges. Already-resolved files are excluded
        // to avoid re-broadcasting via the watch pattern and defeating the surgical match.
        $unknownToGraph = $unparseableMigrations;
        foreach ($nonMigrationPaths as $rel) {
            if (isset($preciselyHandledPages[$rel])) {
                continue;
            }
            if (isset($sharedFilesResolved[$rel])) {
                continue;
            }
            if (isset($staticallyHandledBlade[$rel])) {
                continue;
            }
            if (! isset($this->fileIds[$rel])) {
                if (! is_file($this->projectRoot.'/'.$rel)) {
                    // Deleted file unknown to the graph — no edge ever pointed to it.
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

        // Sibling heuristic: unknown PHP source files may be new files whose graph was inherited from
        // another branch. Run tests that cover neighbouring files in the same directory so framework-
        // discovered files (Listeners, Events, Policies, etc.) aren't silently missed.
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

    public function knowsTest(string $testFile): bool
    {
        $rel = $this->relative($testFile);

        return $rel !== null && isset($this->edges[$rel]);
    }

    /** @return array<int, string> */
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

    public function setResult(string $branch, string $testId, int $status, string $message, float $time, int $assertions = 0, ?string $file = null): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['results'][$testId] = [
            'status' => $status,
            'message' => $message,
            'time' => $time,
            'assertions' => $assertions,
        ];

        if ($file !== null) {
            $rel = $this->relative($file);

            if ($rel !== null) {
                $this->baselines[$branch]['results'][$testId]['file'] = $rel;
            }
        }
    }

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
     * @return array<int, string>
     */
    public function failedOrErroredTestFiles(string $branch, string $fallbackBranch = 'main'): array
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);
        $files = [];

        foreach ($baseline['results'] as $result) {
            $status = $result['status'] ?? null;

            if ($status !== 7 && $status !== 8) {
                continue;
            }

            $file = $result['file'] ?? null;
            if (! is_string($file)) {
                continue;
            }
            if ($file === '') {
                continue;
            }

            $rel = $this->relative($file);

            if ($rel !== null) {
                $files[$rel] = true;
            }
        }

        return array_keys($files);
    }

    public function hasUnlocatedFailuresOrErrors(string $branch, string $fallbackBranch = 'main'): bool
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        foreach ($baseline['results'] as $result) {
            $status = $result['status'] ?? null;

            if ($status !== 7 && $status !== 8) {
                continue;
            }

            $file = $result['file'] ?? null;

            if (! is_string($file) || $file === '' || $this->relative($file) === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $tree  project-relative path → content hash
     */
    public function setLastRunTree(string $branch, array $tree): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['tree'] = $tree;
    }

    // Edges and tree snapshot stay intact; only the run-state is reset.
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
     * @return array{sha: ?string, tree: array<string, string>, results: array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>}
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

    // Empty input is treated as a resolver failure (not "no JS pages") — keep the previous map.
    /**
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

    private function isMigrationPath(string $rel): bool
    {
        return str_starts_with($rel, 'database/migrations/') && str_ends_with($rel, '.php');
    }

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
            'app/Nova/Actions/',
            'app/Nova/Dashboards/',
            'app/Nova/Lenses/',
            'app/Nova/Metrics/',
            'app/Nova/Policies/',
            'app/Nova/Resources/',
            'app/Projectors/',
            'app/Reactors/',
            'database/factories/',
            'database/seeders/',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($rel, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isProjectSourcePhp(string $rel): bool
    {
        return str_ends_with($rel, '.php')
            && ! $this->isBladePath($rel)
            && ! str_starts_with($rel, 'tests/')
            && ! str_starts_with($rel, 'vendor/')
            && ! str_starts_with($rel, 'storage/framework/')
            && ! str_starts_with($rel, 'bootstrap/cache/');
    }

    private function isArchTestFile(string $rel): bool
    {
        return isset($this->archTestFiles()[$rel]);
    }

    /**
     * @return array<string, true>
     */
    private function archTestFiles(): array
    {
        if ($this->archTestFiles !== null) {
            return $this->archTestFiles;
        }

        $this->archTestFiles = [];
        $repo = TestSuite::getInstance()->tests;

        foreach ($repo->getFilenames() as $filename) {
            $factory = $repo->get($filename);

            if (! $factory instanceof TestCaseFactory) {
                continue;
            }

            foreach ($factory->methods as $method) {
                if (! $this->methodHasGroup($method, 'arch')) {
                    continue;
                }

                $rel = $this->relative($filename);

                if ($rel !== null) {
                    $this->archTestFiles[$rel] = true;
                }

                break;
            }
        }

        foreach (array_keys($this->edges) as $testFile) {
            if (isset($this->archTestFiles[$testFile])) {
                continue;
            }
            if ($this->testSourceDeclaresArchGroup($testFile)) {
                $this->archTestFiles[$testFile] = true;
            }
        }

        return $this->archTestFiles;
    }

    private function methodHasGroup(object $method, string $group): bool
    {
        if (property_exists($method, 'groups') && is_array($method->groups) && in_array($group, $method->groups, true)) {
            return true;
        }

        if (! property_exists($method, 'attributes') || ! is_array($method->attributes)) {
            return false;
        }

        foreach ($method->attributes as $attribute) {
            if (! is_object($attribute)) {
                continue;
            }
            if (! property_exists($attribute, 'name')) {
                continue;
            }
            if ($attribute->name !== Group::class) {
                continue;
            }
            if (! property_exists($attribute, 'arguments')) {
                continue;
            }

            foreach ($attribute->arguments as $argument) {
                if ($argument === $group) {
                    return true;
                }
            }
        }

        return false;
    }

    private function testSourceDeclaresArchGroup(string $rel): bool
    {
        $source = @file_get_contents($this->projectRoot.'/'.$rel);

        if ($source === false) {
            return false;
        }

        return preg_match('/\barch\s*\(/', $source) === 1
            || preg_match('/->\s*group\s*\(\s*[\'\"]arch[\'\"]/', $source) === 1
            || preg_match('/#\[\s*(?:\\\\)?(?:PHPUnit\\\\Framework\\\\Attributes\\\\)?Group\s*\(\s*[\'\"]arch[\'\"]/', $source) === 1;
    }

    private function isBladePath(string $rel): bool
    {
        return str_starts_with($rel, 'resources/views/') && str_ends_with($rel, '.blade.php');
    }

    private function isBladeComponentPath(string $rel): bool
    {
        return str_starts_with($rel, 'resources/views/components/') && str_ends_with($rel, '.blade.php');
    }

    /**
     * @return list<string> Project-relative test files.
     */
    private function affectedByStaticBladeUsage(string $changedBlade): array
    {
        $ancestors = $this->bladeAncestorsFor($changedBlade);

        if ($ancestors === []) {
            return [];
        }

        $ancestorIds = [];
        foreach ($ancestors as $ancestor) {
            if (isset($this->fileIds[$ancestor])) {
                $ancestorIds[$this->fileIds[$ancestor]] = true;
            }
        }

        if ($ancestorIds === []) {
            return [];
        }

        $affected = [];
        foreach ($this->edges as $testFile => $ids) {
            foreach ($ids as $id) {
                if (isset($ancestorIds[$id])) {
                    $affected[$testFile] = true;

                    break;
                }
            }
        }

        return array_keys($affected);
    }

    /**
     * @return list<string> Project-relative Blade files that statically depend on $changedBlade, directly or transitively.
     */
    private function bladeAncestorsFor(string $changedBlade): array
    {
        $allBladeFiles = $this->allBladeFiles();

        if ($allBladeFiles === []) {
            return [];
        }

        $targets = [$changedBlade => true];
        $ancestors = [];
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($allBladeFiles as $candidate) {
                if (isset($targets[$candidate])) {
                    continue;
                }
                if (isset($ancestors[$candidate])) {
                    continue;
                }

                $source = @file_get_contents($this->projectRoot.'/'.$candidate);
                if ($source === false) {
                    continue;
                }

                foreach (array_keys($targets) as $target) {
                    if ($this->bladeSourceReferences($source, $target)) {
                        $ancestors[$candidate] = true;
                        $targets[$candidate] = true;
                        $changed = true;

                        break;
                    }
                }
            }
        }

        return array_keys($ancestors);
    }

    /**
     * @return list<string>
     */
    private function allBladeFiles(): array
    {
        $views = $this->projectRoot.'/resources/views';

        if (! is_dir($views)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($views, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }
            if (! $file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($this->projectRoot) + 1));
        }

        sort($files);

        return $files;
    }

    private function bladeSourceReferences(string $source, string $targetBlade): bool
    {
        $view = $this->viewNameForBlade($targetBlade);

        if ($view !== null) {
            $quoted = preg_quote($view, '#');

            if (preg_match('#@(include|includeIf|includeWhen|includeUnless|extends|component|each)\s*\([^)]*[\'\"]'.$quoted.'[\'\"]#', $source) === 1) {
                return true;
            }

            if (preg_match('#\b(view|View::make)\s*\(\s*[\'\"]'.$quoted.'[\'\"]#', $source) === 1) {
                return true;
            }
        }

        foreach ($this->componentNamesForBlade($targetBlade) as $component) {
            $quoted = preg_quote($component, '#');

            if (preg_match('#<x-'.$quoted.'(?=[\s>/.:])#i', $source) === 1) {
                return true;
            }
        }

        return false;
    }

    private function viewNameForBlade(string $rel): ?string
    {
        if (! $this->isBladePath($rel)) {
            return null;
        }

        $tail = substr($rel, strlen('resources/views/'));
        $tail = substr($tail, 0, -strlen('.blade.php'));

        return str_replace('/', '.', $tail);
    }

    /**
     * @return list<string>
     */
    private function componentNamesForBlade(string $rel): array
    {
        if (! $this->isBladeComponentPath($rel)) {
            return [];
        }

        $tail = substr($rel, strlen('resources/views/components/'));
        $tail = substr($tail, 0, -strlen('.blade.php'));
        $name = str_replace('/', '.', $tail);

        return $name === '' ? [] : [$name, str_replace('_', '-', $name)];
    }

    /** @return list<string> */
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

    // Both `Pages/` and `pages/` are accepted — git paths are case-sensitive on Linux.
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

    private function isGlobalFrontendRuntimePath(string $rel): bool
    {
        if (! str_starts_with($rel, 'resources/js/')) {
            return false;
        }

        $tail = substr($rel, strlen('resources/js/'));
        $dot = strrpos($tail, '.');

        if ($dot === false) {
            return false;
        }

        $name = substr($tail, 0, $dot);
        $extension = substr($tail, $dot + 1);

        return in_array($extension, ['js', 'jsx', 'ts', 'tsx', 'vue', 'svelte'], true)
            && in_array($name, ['App', 'app', 'bootstrap', 'echo', 'favicon'], true);
    }

    /** @param  array<string, array<int, string>>  $edges */
    private function anyTestUses(array $edges, string $component): bool
    {
        foreach ($edges as $components) {
            if (in_array($component, $components, true)) {
                return true;
            }
        }

        return false;
    }

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

    // Accepts both absolute paths (from coverage drivers) and project-relative paths (from git diff).
    // Relative paths are NOT resolved via realpath() because CWD is not guaranteed to be the project root.
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

            // Always forward slashes — git always uses them; Windows backslashes would never match.
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root)));
        } else {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $path);

            while (str_starts_with($relative, './')) {
                $relative = substr($relative, 2);
            }
        }

        if (str_starts_with($relative, 'vendor/')) {
            return null;
        }

        return $relative;
    }
}
