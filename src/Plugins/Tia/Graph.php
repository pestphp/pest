<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Factories\TestCaseFactory;
use Pest\Factories\TestCaseMethodFactory;
use Pest\Support\Container;
use Pest\Support\View;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestStatus\TestStatus;
use PHPUnit\TextUI\Configuration\Registry;

/**
 * @internal
 */
final class Graph
{
    /**
     * @var array<string, string>
     */
    private const array LIVEWIRE_GENERATED_PATHS = [
        '/livewire/views/' => '.blade.php',
        '/livewire/placeholders/' => '.blade.php',
        '/livewire/classes/' => '.php',
    ];

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
     *     complete?: bool,
     *     results: array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>
     * }>
     */
    private array $baselines = [];

    private string $fallbackBranch = 'main';

    private readonly string $projectRoot;

    /** @var array<string, true>|null */
    private ?array $archTestFiles = null;

    /** @var array<string, string|false> */
    private array $realpathCache = [];

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
        [$migrationPaths, $nonMigrationPaths] = $this->partitionChangedPaths($changedFiles);

        $affectedSet = [];

        $unparseableMigrations = $this->applyMigrationChanges($migrationPaths, $affectedSet);

        [$globalFrontendRuntimeFiles, $preciselyHandledPages, $sharedFilesResolved]
            = $this->applyInertiaChanges($nonMigrationPaths, $affectedSet);

        $unknownSourceDirs = $this->applyPhpEdgeChanges($nonMigrationPaths, $affectedSet);

        $this->applyTestFileChanges($nonMigrationPaths, $affectedSet);

        $handledBlade = $this->applyBladeStaticChanges($nonMigrationPaths, $affectedSet)
            + $this->applyLivewireComponentChanges($nonMigrationPaths, $affectedSet);

        $this->applyWatchPatternFallback(
            $nonMigrationPaths,
            $unparseableMigrations,
            $preciselyHandledPages,
            $sharedFilesResolved,
            $handledBlade,
            $affectedSet,
        );

        $this->applyUnknownSourceDirs($unknownSourceDirs, $affectedSet);

        return array_keys($affectedSet);
    }

    /**
     * @param  array<int, string>  $testFiles  Project-relative paths.
     * @return list<string>
     */
    public function testFilesOnDisk(array $testFiles): array
    {
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $onDisk = [];

        foreach ($testFiles as $testFile) {
            if (is_file($root.$testFile)) {
                $onDisk[] = $testFile;
            }
        }

        return $onDisk;
    }

    /**
     * @param  array<int, string>  $changedFiles
     * @return array{0: list<string>, 1: list<string>}
     */
    private function partitionChangedPaths(array $changedFiles): array
    {
        $migrations = [];
        $nonMigrations = [];

        foreach ($changedFiles as $file) {
            $rel = $this->relative($file);

            if ($rel === null) {
                continue;
            }

            if ($this->isMigrationPath($rel)) {
                $migrations[] = $rel;
            } else {
                $nonMigrations[] = $rel;
            }
        }

        return [$migrations, $nonMigrations];
    }

    /**
     * @param  list<string>  $migrationPaths
     * @param  array<string, true>  $affectedSet
     * @return list<string> Unparseable migrations (caller treats as unknown-to-graph).
     */
    private function applyMigrationChanges(array $migrationPaths, array &$affectedSet): array
    {
        if ($this->testTables === []) {
            return $migrationPaths;
        }

        $changedTables = [];
        $unparseable = [];

        foreach ($migrationPaths as $rel) {
            $tables = $this->tablesForMigration($rel);

            if ($tables === []) {
                $unparseable[] = $rel;

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

        return $unparseable;
    }

    /**
     * @param  list<string>  $nonMigrationPaths
     * @param  array<string, true>  $affectedSet
     * @return array{0: array<string, true>, 1: array<string, true>, 2: array<string, true>}
     */
    private function applyInertiaChanges(array $nonMigrationPaths, array &$affectedSet): array
    {
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
            $this->resolveNewJsFiles($newJsFiles, $changedComponents, $sharedFilesResolved);
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

        return [$globalFrontendRuntimeFiles, $preciselyHandledPages, $sharedFilesResolved];
    }

    /**
     * @param  list<string>  $newJsFiles
     * @param  array<string, true>  $changedComponents
     * @param  array<string, true>  $sharedFilesResolved
     */
    private function resolveNewJsFiles(array $newJsFiles, array &$changedComponents, array &$sharedFilesResolved): void
    {
        $freshMap = JsModuleGraph::buildStrict($this->projectRoot);

        if ($freshMap === null) {
            View::render('components.badge', [
                'type' => 'WARN',
                'content' => sprintf(
                    'Vite resolver unavailable — falling back to watch pattern for %d new JS file(s).',
                    count($newJsFiles),
                ),
            ]);

            return;
        }

        foreach ($newJsFiles as $rel) {
            $pages = $freshMap[$rel] ?? [];

            if ($pages === []) {
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

    /**
     * @param  list<string>  $nonMigrationPaths
     * @param  array<string, true>  $affectedSet
     * @return array<string, true> Unknown source dirs (sibling-heuristic).
     */
    private function applyPhpEdgeChanges(array $nonMigrationPaths, array &$affectedSet): array
    {
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
                if (! is_file($this->projectRoot.'/'.$rel)) {
                    continue;
                }

                if ($this->usesSiblingHeuristicForUnknownPhp($rel)) {
                    $unknownSourceDirs[dirname($rel)] = true;
                }
            }
        }

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

        return $unknownSourceDirs;
    }

    /**
     * @param  list<string>  $nonMigrationPaths
     * @param  array<string, true>  $affectedSet
     */
    private function applyTestFileChanges(array $nonMigrationPaths, array &$affectedSet): void
    {
        $testPaths = TestPaths::fromProjectRoot($this->projectRoot);

        foreach ($nonMigrationPaths as $rel) {
            if (isset($affectedSet[$rel])) {
                continue;
            }
            if (! $testPaths->isTestFile($rel)) {
                continue;
            }
            if (! is_file($this->projectRoot.'/'.$rel)) {
                continue;
            }
            $affectedSet[$rel] = true;
        }
    }

    /**
     * Unknown Blade files: walk static references (@include, @extends, <x-*>) up to rendered.
     *
     * @param  list<string>  $nonMigrationPaths
     * @param  array<string, true>  $affectedSet
     * @return array<string, true>
     */
    private function applyBladeStaticChanges(array $nonMigrationPaths, array &$affectedSet): array
    {
        $staticallyHandled = [];

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

                $staticallyHandled[$rel] = true;
            }
        }

        return $staticallyHandled;
    }

    /**
     * @param  list<string>  $nonMigrationPaths
     * @param  array<string, true>  $affectedSet
     * @return array<string, true>
     */
    private function applyLivewireComponentChanges(array $nonMigrationPaths, array &$affectedSet): array
    {
        $generatedIds = $this->livewireGeneratedFileIds();

        if ($generatedIds === []) {
            return [];
        }

        /** @var array<int, array<string, true>> $sourcesByGeneratedId */
        $sourcesByGeneratedId = [];

        foreach ($nonMigrationPaths as $rel) {
            foreach ($this->livewireSourcePaths($rel) as $sourcePath) {
                foreach ($generatedIds[$this->livewireHash($sourcePath)] ?? [] as $id) {
                    $sourcesByGeneratedId[$id][$rel] = true;
                }
            }
        }

        if ($sourcesByGeneratedId === []) {
            return [];
        }

        $handled = [];

        foreach ($this->edges as $testFile => $ids) {
            foreach ($ids as $id) {
                if (! isset($sourcesByGeneratedId[$id])) {
                    continue;
                }

                $affectedSet[$testFile] = true;
                $handled += $sourcesByGeneratedId[$id];
            }
        }

        return $handled;
    }

    /**
     * @return list<string>
     */
    private function livewireSourcePaths(string $rel): array
    {
        $sourcePaths = [];

        if (str_ends_with($rel, '.blade.php')) {
            $sourcePaths[] = $rel;
        }

        $componentDirectory = dirname($rel);

        if ($this->isLivewireMultiFileDirectory($componentDirectory)) {
            $sourcePaths[] = $componentDirectory;
        }

        return $sourcePaths;
    }

    private function isLivewireMultiFileDirectory(string $componentDirectory): bool
    {
        $directoryName = basename($componentDirectory);

        if (str_contains($directoryName, 'index')) {
            $directoryName = 'index';
        }

        $componentName = preg_replace('/⚡[\x{FE0E}\x{FE0F}]?/u', '', $directoryName);

        if ($componentName === null || $componentName === '') {
            return false;
        }

        $source = $this->projectRoot.'/'.$componentDirectory.'/'.$componentName;

        return is_file($source.'.php') && is_file($source.'.blade.php');
    }

    private function livewireHash(string $sourcePath): string
    {
        return substr(md5(DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $sourcePath)), 0, 8);
    }

    /**
     * @return array<string, list<int>>
     */
    private function livewireGeneratedFileIds(): array
    {
        $generated = [];

        foreach ($this->fileIds as $path => $id) {
            $hash = $this->livewireGeneratedHash($path);

            if ($hash === null) {
                continue;
            }

            $generated[$hash][] = $id;
        }

        return $generated;
    }

    private function livewireGeneratedHash(string $path): ?string
    {
        $normalized = '/'.ltrim($path, '/');

        foreach (self::LIVEWIRE_GENERATED_PATHS as $directory => $extension) {
            if (! str_ends_with($normalized, $extension)) {
                continue;
            }

            $position = strrpos($normalized, $directory);

            if ($position === false) {
                continue;
            }

            $hash = substr($normalized, $position + strlen($directory), -strlen($extension));

            if (preg_match('/^[0-9a-f]{8}$/', $hash) === 1) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $nonMigrationPaths
     * @param  list<string>  $unparseableMigrations
     * @param  array<string, true>  $preciselyHandledPages
     * @param  array<string, true>  $sharedFilesResolved
     * @param  array<string, true>  $handledBlade
     * @param  array<string, true>  $affectedSet
     */
    private function applyWatchPatternFallback(
        array $nonMigrationPaths,
        array $unparseableMigrations,
        array $preciselyHandledPages,
        array $sharedFilesResolved,
        array $handledBlade,
        array &$affectedSet,
    ): void {
        $unknownToGraph = $unparseableMigrations;

        foreach ($nonMigrationPaths as $rel) {
            if (isset($preciselyHandledPages[$rel])) {
                continue;
            }
            if (isset($sharedFilesResolved[$rel])) {
                continue;
            }
            if (isset($handledBlade[$rel])) {
                continue;
            }
            if (! isset($this->fileIds[$rel])) {
                if (! is_file($this->projectRoot.'/'.$rel)) {
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
    }

    /**
     * @param  array<string, true>  $unknownSourceDirs
     * @param  array<string, true>  $affectedSet
     */
    private function applyUnknownSourceDirs(array $unknownSourceDirs, array &$affectedSet): void
    {
        if ($unknownSourceDirs === []) {
            return;
        }

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

    public function setFallbackBranch(string $branch): void
    {
        $this->fallbackBranch = $branch;
    }

    public function recordedAtSha(string $branch, ?string $fallbackBranch = null): ?string
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

        $entry = [
            'status' => $status,
            'message' => $message,
            'time' => $time,
            'assertions' => $assertions,
        ];

        if ($file !== null) {
            $rel = $this->relative($file);

            if ($rel !== null) {
                $entry['file'] = $rel;
            }
        }

        $this->baselines[$branch]['results'][$testId] = $entry;
    }

    public function getAssertions(string $branch, string $testId, ?string $fallbackBranch = null): ?int
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        if (! isset($baseline['results'][$testId]['assertions'])) {
            return null;
        }

        return $baseline['results'][$testId]['assertions'];
    }

    public function getTime(string $branch, string $testId, ?string $fallbackBranch = null): ?float
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        if (! isset($baseline['results'][$testId]['time'])) {
            return null;
        }

        return $baseline['results'][$testId]['time'];
    }

    public function getResult(string $branch, string $testId, ?string $fallbackBranch = null): ?TestStatus
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        if (! isset($baseline['results'][$testId])) {
            return null;
        }

        $r = $baseline['results'][$testId];

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
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function testFilesToRerun(string $branch, ?string $fallbackBranch = null): array
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);
        $files = [];

        foreach ($baseline['results'] as $result) {
            if (! $this->shouldRerun($result['status'])) {
                continue;
            }

            $file = $result['file'] ?? null;
            if ($file === null) {
                continue;
            }
            if ($file === '') {
                continue;
            }

            $rel = $this->relative($file);

            if ($rel !== null && is_file($this->projectRoot.'/'.$rel)) {
                $files[$rel] = true;
            }
        }

        return array_keys($files);
    }

    public function hasUnlocatedTestsToRerun(string $branch, ?string $fallbackBranch = null): bool
    {
        $baseline = $this->baselineFor($branch, $fallbackBranch);

        foreach ($baseline['results'] as $result) {
            if (! $this->shouldRerun($result['status'])) {
                continue;
            }

            $file = $result['file'] ?? null;

            if ($file === null || $file === '') {
                return true;
            }

            if ($this->relative($file) === null) {
                return true;
            }
        }

        return false;
    }

    private function shouldRerun(int $status): bool
    {
        return $this->shouldRerunStatus(TestStatus::from($status));
    }

    public function shouldRerunStatus(TestStatus $testStatus): bool
    {
        if ($testStatus->isFailure() || $testStatus->isError()) {
            return true;
        }

        if ($testStatus->isUnknown()) {
            return true;
        }

        $configuration = Registry::get();

        if ($testStatus->isRisky()) {
            return $configuration->failOnRisky();
        }

        if ($testStatus->isWarning()) {
            if ($configuration->failOnWarning()) {
                return true;
            }

            return $configuration->displayDetailsOnTestsThatTriggerWarnings();
        }

        if ($testStatus->isNotice()) {
            if ($configuration->failOnNotice()) {
                return true;
            }

            return $configuration->displayDetailsOnTestsThatTriggerNotices();
        }

        if ($testStatus->isDeprecation()) {
            if ($configuration->failOnDeprecation()) {
                return true;
            }

            return $configuration->displayDetailsOnTestsThatTriggerDeprecations();
        }

        if ($testStatus->isIncomplete()) {
            if ($configuration->failOnIncomplete()) {
                return true;
            }

            return $configuration->displayDetailsOnIncompleteTests();
        }

        if ($testStatus->isSkipped()) {
            if ($configuration->failOnSkipped()) {
                return true;
            }

            return $configuration->displayDetailsOnSkippedTests();
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

    public function clearResults(string $branch): void
    {
        $this->ensureBaseline($branch);
        $this->baselines[$branch]['results'] = [];
    }

    /**
     * @return array<string, string>
     */
    public function lastRunTree(string $branch, ?string $fallbackBranch = null): array
    {
        return $this->baselineFor($branch, $fallbackBranch)['tree'];
    }

    /**
     * @return array{sha: ?string, tree: array<string, string>, complete?: bool, results: array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>}
     */
    private function baselineFor(string $branch, ?string $fallbackBranch): array
    {
        $fallbackBranch ??= $this->fallbackBranch;

        $fallback = $branch !== $fallbackBranch ? ($this->baselines[$fallbackBranch] ?? null) : null;
        $own = $this->baselines[$branch] ?? null;

        if ($own === null) {
            return $fallback ?? ['sha' => null, 'tree' => [], 'results' => []];
        }

        if ($fallback === null) {
            return $own;
        }

        $under = ($own['complete'] ?? false) === true
            ? $this->withoutFilesCoveredBy($fallback['results'], $own['results'])
            : $fallback['results'];

        return [
            'sha' => $own['sha'] ?? $fallback['sha'],
            'tree' => $own['tree'] !== [] ? $own['tree'] : $fallback['tree'],
            'results' => array_replace($under, $own['results']),
        ];
    }

    /**
     * @param  array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>  $results
     * @param  array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>  $authoritative
     * @return array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>
     */
    private function withoutFilesCoveredBy(array $results, array $authoritative): array
    {
        $covered = [];

        foreach ($authoritative as $entry) {
            $file = $entry['file'] ?? null;

            if (is_string($file) && $file !== '') {
                $covered[$file] = true;
            }
        }

        if ($covered === []) {
            return $results;
        }

        foreach ($results as $testId => $entry) {
            $file = $entry['file'] ?? null;

            if (is_string($file) && isset($covered[$file])) {
                unset($results[$testId]);
            }
        }

        return $results;
    }

    private function ensureBaseline(string $branch): void
    {
        if (! isset($this->baselines[$branch])) {
            $this->baselines[$branch] = ['sha' => null, 'tree' => [], 'results' => []];
        }
    }

    /**
     * @param  array<string, array<int, string>>  $testToFiles
     * @param  bool  $keepExisting  Leave already-recorded edge sets alone.
     */
    public function replaceEdges(array $testToFiles, bool $keepExisting = false): void
    {
        foreach ($testToFiles as $testFile => $sources) {
            $testRel = $this->relative($testFile);

            if ($testRel === null) {
                continue;
            }

            if ($keepExisting && ($this->edges[$testRel] ?? []) !== []) {
                continue;
            }

            $this->edges[$testRel] = [];

            foreach ($sources as $source) {
                $this->link($testFile, $source);
            }

            $this->edges[$testRel] = array_values(array_unique($this->edges[$testRel]));
        }
    }

    /**
     * {@see self::knowsTest()} reports it as unknown and it re-runs on every TIA
     * {@see self::applyTestFileChanges()}.
     *
     * @param  array<int, string>  $testFiles  Absolute or project-relative test file paths.
     */
    public function markKnownTestFiles(array $testFiles): void
    {
        foreach ($testFiles as $testFile) {
            $rel = $this->relative($testFile);

            if ($rel === null) {
                continue;
            }

            if (! isset($this->edges[$rel])) {
                $this->edges[$rel] = [];
            }
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

    private function methodHasGroup(TestCaseMethodFactory $method, string $group): bool
    {
        if (in_array($group, $method->groups, true)) {
            return true;
        }

        foreach ($method->attributes as $attribute) {
            if ($attribute->name !== Group::class) {
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
            assert($file instanceof \SplFileInfo);

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

        if ($name === '') {
            return [];
        }

        $names = [$name, str_replace('_', '-', $name)];

        if (str_ends_with($name, '.index') && $name !== '.index') {
            $base = substr($name, 0, -strlen('.index'));

            $names[] = $base;
            $names[] = str_replace('_', '-', $base);
        }

        return array_values(array_unique($names));
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
        return array_any($edges, fn (array $components): bool => in_array($component, $components, true));
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

    public function markBaselineComplete(string $branch): void
    {
        if (isset($this->baselines[$branch])) {
            $this->baselines[$branch]['complete'] = true;
        }
    }

    public function pruneResultsForMissingFiles(string $branch): void
    {
        if (! isset($this->baselines[$branch]['results'])) {
            return;
        }

        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        foreach ($this->baselines[$branch]['results'] as $testId => $result) {
            $file = $result['file'] ?? null;
            if (! is_string($file)) {
                continue;
            }
            if ($file === '') {
                continue;
            }

            $rel = $this->relative($file);
            if ($rel === null) {
                continue;
            }
            if (is_file($root.$rel)) {
                continue;
            }

            unset($this->baselines[$branch]['results'][$testId]);
        }
    }

    /**
     * @param  array<int, string>  $keep
     */
    public function pruneMissingBranches(array $keep): void
    {
        $survivors = array_fill_keys($keep, true);

        foreach (array_keys($this->baselines) as $branch) {
            if (! isset($survivors[$branch])) {
                unset($this->baselines[$branch]);
            }
        }
    }

    /**
     * @param  array<int, string>  $touchedFiles  Absolute or project-relative paths.
     * @param  array<int, string>  $keepTestIds  Test IDs that produced a result this run.
     */
    public function pruneStaleResults(string $branch, array $touchedFiles, array $keepTestIds): void
    {
        if (! isset($this->baselines[$branch]['results'])) {
            return;
        }

        $touched = [];
        foreach ($touchedFiles as $file) {
            $rel = $this->relative($file);

            if ($rel !== null) {
                $touched[$rel] = true;
            }
        }

        if ($touched === []) {
            return;
        }

        $keep = array_fill_keys($keepTestIds, true);

        foreach ($this->baselines[$branch]['results'] as $testId => $result) {
            $file = $result['file'] ?? null;
            if (! is_string($file)) {
                continue;
            }
            if (! isset($touched[$file])) {
                continue;
            }

            if (isset($keep[$testId])) {
                continue;
            }

            unset($this->baselines[$branch]['results'][$testId]);
        }
    }

    /**
     * @return list<string>
     */
    public static function branchesIn(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! is_array($data['baselines'] ?? null)) {
            return [];
        }

        $branches = [];

        foreach (array_keys($data['baselines']) as $branch) {
            if (is_string($branch) && $branch !== '') {
                $branches[] = $branch;
            }
        }

        return $branches;
    }

    public static function decode(string $json, string $projectRoot): ?self
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ($data['schema'] ?? null) !== 1) {
            return null;
        }

        $graph = new self($projectRoot);
        $graph->fingerprint = is_array($data['fingerprint'] ?? null) ? $data['fingerprint'] : [];
        $graph->files = self::decodeFiles($data['files'] ?? null);
        $graph->fileIds = array_flip($graph->files);
        $graph->edges = self::decodeEdges($data['edges'] ?? null);
        $graph->baselines = self::decodeBaselines($data['baselines'] ?? null);

        $graph->testTables = self::decodeStringMap($data['test_tables'] ?? null);
        $graph->testInertiaComponents = self::decodeStringMap($data['test_inertia_components'] ?? null);
        $graph->jsFileToComponents = self::decodeStringMap($data['js_file_to_components'] ?? null);

        return $graph;
    }

    /**
     * @return array<int, string>
     */
    private static function decodeFiles(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        $files = [];

        foreach ($section as $path) {
            if (is_string($path) && $path !== '') {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private static function decodeEdges(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        $edges = [];

        foreach ($section as $key => $ids) {
            $testFile = (string) $key;

            if ($testFile === '') {
                continue;
            }
            if (! is_array($ids)) {
                continue;
            }

            $clean = [];

            foreach ($ids as $id) {
                if (is_int($id)) {
                    $clean[] = $id;
                }
            }

            $edges[$testFile] = $clean;
        }

        return $edges;
    }

    /**
     * @return array<string, array{sha: ?string, tree: array<string, string>, complete?: bool, results: array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>}>
     */
    private static function decodeBaselines(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        $baselines = [];

        foreach ($section as $key => $baseline) {
            $branch = (string) $key;

            if ($branch === '') {
                continue;
            }
            if (! is_array($baseline)) {
                continue;
            }

            $sha = $baseline['sha'] ?? null;
            $tree = [];

            if (is_array($baseline['tree'] ?? null)) {
                foreach ($baseline['tree'] as $path => $hash) {
                    if (is_string($path) && is_string($hash)) {
                        $tree[$path] = $hash;
                    }
                }
            }

            $baselines[$branch] = [
                'sha' => is_string($sha) ? $sha : null,
                'tree' => $tree,
                'results' => self::decodeResults($baseline['results'] ?? null),
            ];

            if (($baseline['complete'] ?? null) === true) {
                $baselines[$branch]['complete'] = true;
            }
        }

        return $baselines;
    }

    /**
     * @return array<string, array{status: int, message: string, time: float, assertions?: int, file?: string}>
     */
    private static function decodeResults(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        $results = [];

        foreach ($section as $key => $entry) {
            $testId = (string) $key;

            if ($testId === '') {
                continue;
            }
            if (! is_array($entry)) {
                continue;
            }
            if (! is_int($entry['status'] ?? null)) {
                continue;
            }

            $time = $entry['time'] ?? null;

            $result = [
                'status' => $entry['status'],
                'message' => is_string($entry['message'] ?? null) ? $entry['message'] : '',
                'time' => is_int($time) || is_float($time) ? (float) $time : 0.0,
            ];

            if (is_int($entry['assertions'] ?? null)) {
                $result['assertions'] = $entry['assertions'];
            }

            if (is_string($entry['file'] ?? null) && $entry['file'] !== '') {
                $result['file'] = $entry['file'];
            }

            $results[$testId] = $result;
        }

        return $results;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function decodeStringMap(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        $out = [];

        foreach ($section as $key => $values) {
            if (! is_string($key)) {
                continue;
            }
            if ($key === '') {
                continue;
            }
            if (! is_array($values)) {
                continue;
            }

            $names = [];

            foreach ($values as $value) {
                if (is_string($value) && $value !== '') {
                    $names[] = $value;
                }
            }

            if ($names !== []) {
                $out[$key] = $names;
            }
        }

        return $out;
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
            || (strlen($path) >= 2 && $path[1] === ':');
        if ($isAbsolute) {
            if (array_key_exists($path, $this->realpathCache)) {
                $real = $this->realpathCache[$path];
            } else {
                $real = $this->realpathCache[$path] = @realpath($path);
            }

            if ($real === false) {
                $real = $path;
            }

            if (! str_starts_with($real, $root)) {
                return null;
            }

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
