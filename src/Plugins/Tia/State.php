<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Shared TIA replay state consulted by Pest's `Testable` trait at runtime.
 *
 * Why a singleton: the plugin runs in `handleArguments` (before tests are
 * discovered), but the actual replay decision has to happen when each test
 * boots (`setUp` / `__runTest`). Those call sites are inside a trait that
 * has no easy way to inject dependencies, so they reach into this state
 * holder.
 *
 * Decision: a test file replays its previous pass iff
 *   1. TIA replay mode is active,
 *   2. the file is **known** to the dependency graph,
 *   3. the file is **not** in the affected set (its deps are unchanged),
 *   4. it was **not** in the previous run's defect list (only cached passes
 *      replay; previously-failing tests rerun so users see current state).
 *
 * Points 1-3 live in this class. Point 4 uses PHPUnit's own
 * `DefaultResultCache`, queried at decision time.
 *
 * @internal
 */
final class State
{
    private static ?self $instance = null;

    private bool $replayMode = false;

    /**
     * Keys are project-relative test file paths. Affected = must rerun.
     *
     * @var array<string, true>
     */
    private array $affectedFiles = [];

    /**
     * Keys are project-relative test file paths. Known = recorded in graph.
     *
     * @var array<string, true>
     */
    private array $knownFiles = [];

    /**
     * Test ids (class::method) that were in the previous run's defect list.
     *
     * @var array<string, true>
     */
    private array $previousDefects = [];

    /**
     * Canonicalised project root used for relative-path calculations.
     */
    private string $projectRoot = '';

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Turns on replay mode with the given graph + affected set.
     *
     * @param  array<string, true>  $affectedFiles
     * @param  array<string, true>  $previousDefects
     */
    public function activate(string $projectRoot, Graph $graph, array $affectedFiles, array $previousDefects): void
    {
        $real = @realpath($projectRoot);

        $this->projectRoot = $real !== false ? $real : $projectRoot;
        $this->replayMode = true;
        $this->affectedFiles = $affectedFiles;
        $this->previousDefects = $previousDefects;

        // Pre-compute the known set from the graph so per-test lookups stay
        // O(1). Iterating edges once here beats calling `Graph::knowsTest`
        // from every test's `setUp`.
        $this->knownFiles = [];

        foreach ($graph->allTestFiles() as $rel) {
            $this->knownFiles[$rel] = true;
        }
    }

    public function isReplayMode(): bool
    {
        return $this->replayMode;
    }

    /**
     * Returns `true` when the given absolute test file should replay its
     * previous passing result instead of re-executing. `$testId` may be
     * `null` when the caller cannot cheaply determine it (e.g. early in
     * `setUp` before PHPUnit has published the name) — in that case we
     * replay iff the file is safe at the file level, and `__runTest` will
     * repeat the check with a proper id.
     */
    public function shouldReplayFromCache(string $absoluteTestFile, ?string $testId = null): bool
    {
        if (! $this->replayMode) {
            return false;
        }

        $rel = $this->relative($absoluteTestFile);

        if ($rel === null) {
            return false;
        }

        if (! isset($this->knownFiles[$rel])) {
            return false;
        }

        if (isset($this->affectedFiles[$rel])) {
            return false;
        }

        if ($testId !== null && isset($this->previousDefects[$testId])) {
            return false;
        }

        return true;
    }

    public function reset(): void
    {
        $this->replayMode = false;
        $this->affectedFiles = [];
        $this->knownFiles = [];
        $this->previousDefects = [];
        $this->projectRoot = '';
    }

    private function relative(string $path): ?string
    {
        if ($path === '' || $this->projectRoot === '') {
            return null;
        }

        $real = @realpath($path);

        if ($real === false) {
            $real = $path;
        }

        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($real, $root)) {
            return null;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root)));
    }
}
