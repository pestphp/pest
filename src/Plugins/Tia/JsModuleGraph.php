<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
final class JsModuleGraph
{
    private const int NODE_TIMEOUT_SECONDS = 25;

    private const string CACHE_FILE = 'js-module-graph.cache.json';

    /** Active warmer subprocess, or null when none is in flight. */
    private static ?Process $warmer = null;

    /** Fingerprint the warmer was started against — used to detect drift between warm and build. */
    private static ?string $warmerFingerprint = null;

    /** True when the warmer found a fresh cache and skipped spawning Node. */
    private static bool $warmerCacheHit = false;

    /** Project root the warmer was launched for. */
    private static ?string $warmerProjectRoot = null;

    /**
     * Kicks off the Node helper in the background, so by the time
     * `build()` is called at flush time the result is (usually) already
     * sitting on stdout. Idempotent — a second call while a warmer is
     * already in flight is a no-op. Cheap when the cache is fresh: it
     * checks the fingerprint first and skips the subprocess.
     *
     * Safe to call from any TIA entry point that will eventually write
     * the graph from the main process. Workers must NOT call this — they
     * don't flush the graph and would duplicate the Node bootstrap on
     * every worker.
     */
    public static function warmInBackground(string $projectRoot): void
    {
        if (self::$warmer !== null || self::$warmerCacheHit) {
            return;
        }

        if (! self::isApplicable($projectRoot)) {
            return;
        }

        $fingerprint = self::fingerprint($projectRoot);

        if ($fingerprint !== null && self::readCache($projectRoot, $fingerprint) !== null) {
            self::$warmerCacheHit = true;
            self::$warmerFingerprint = $fingerprint;
            self::$warmerProjectRoot = $projectRoot;

            return;
        }

        $process = self::buildNodeProcess($projectRoot);

        if ($process === null) {
            return;
        }

        try {
            $process->start();
        } catch (\Throwable) {
            return;
        }

        self::$warmer = $process;
        self::$warmerFingerprint = $fingerprint;
        self::$warmerProjectRoot = $projectRoot;

        register_shutdown_function(self::reapWarmer(...));
    }

    /**
     * @return array<string, list<string>> project-relative source path → sorted list of page component names
     */
    public static function build(string $projectRoot): array
    {
        $result = self::resolve($projectRoot);

        return $result ?? [];
    }

    /**
     * Strict variant — returns null when the Node resolver isn't
     * available, so callers can distinguish "Vite says nothing imports
     * this file" (empty list) from "we couldn't ask Vite" (null).
     *
     * Used at replay time when we need to *trust a negative result*
     * (i.e., "no page imports this file, so it's orphan, safe to skip").
     *
     * @return array<string, list<string>>|null
     */
    public static function buildStrict(string $projectRoot): ?array
    {
        return self::resolve($projectRoot);
    }

    /**
     * True when the project looks like a Vite + Node project we can
     * ask for a module graph. Gate for callers that want to skip the
     * resolver entirely on non-Vite apps.
     */
    public static function isApplicable(string $projectRoot): bool
    {
        if (! self::hasViteConfig($projectRoot)) {
            return false;
        }

        // Both the classic Inertia-Vue (`Pages/`) and the Laravel React
        // starter kit (`pages/`) conventions are accepted — projects
        // running on a case-sensitive filesystem (Linux CI) get
        // exactly one of the two, and we shouldn't refuse to walk the
        // graph based on which one it picks.
        foreach (['Pages', 'pages'] as $dir) {
            if (is_dir($projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.$dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function resolve(string $projectRoot): ?array
    {
        $fingerprint = self::fingerprint($projectRoot);

        if ($fingerprint !== null) {
            $cached = self::readCache($projectRoot, $fingerprint);

            if ($cached !== null) {
                self::reapWarmer();

                return $cached;
            }
        }

        // Pick up the warmer when it was launched against the same
        // fingerprint and project root. Drift between warm and build
        // (rare — would require a JS file to change mid-test-run)
        // discards the warmer and re-runs synchronously.
        if (self::$warmerCacheHit
            && self::$warmerFingerprint === $fingerprint
            && self::$warmerProjectRoot === $projectRoot
            && $fingerprint !== null) {
            $cached = self::readCache($projectRoot, $fingerprint);
            self::$warmerCacheHit = false;
            self::$warmerFingerprint = null;
            self::$warmerProjectRoot = null;

            if ($cached !== null) {
                return $cached;
            }
        }

        if (self::$warmer !== null
            && self::$warmerFingerprint === $fingerprint
            && self::$warmerProjectRoot === $projectRoot) {
            $process = self::$warmer;
            self::$warmer = null;
            self::$warmerFingerprint = null;
            self::$warmerProjectRoot = null;

            try {
                $process->wait();
            } catch (\Throwable) {
                // fall through to synchronous run
                $process = null;
            }

            if ($process !== null && $process->isSuccessful()) {
                $result = self::parseNodeOutput($process->getOutput());

                if ($result !== null) {
                    if ($fingerprint !== null) {
                        self::writeCache($projectRoot, $fingerprint, $result);
                    }

                    return $result;
                }
            }
        } else {
            // Different fingerprint or different project root: discard
            // any stale warmer before we start a fresh run.
            self::reapWarmer();
        }

        $viaNode = self::runNodeSync($projectRoot);

        if ($viaNode !== null && $fingerprint !== null) {
            self::writeCache($projectRoot, $fingerprint, $viaNode);
        }

        return $viaNode;
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function runNodeSync(string $projectRoot): ?array
    {
        $process = self::buildNodeProcess($projectRoot);

        if ($process === null) {
            return null;
        }

        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return self::parseNodeOutput($process->getOutput());
    }

    private static function buildNodeProcess(string $projectRoot): ?Process
    {
        if (! self::hasViteConfig($projectRoot)) {
            return null;
        }

        if (! is_dir($projectRoot.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.'vite')) {
            return null;
        }

        $nodeBinary = (new ExecutableFinder)->find('node');

        if ($nodeBinary === null) {
            return null;
        }

        $helperPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest-tia-vite-deps.mjs';

        if (! is_file($helperPath)) {
            return null;
        }

        // Tell the Node helper which casing this project uses for its
        // pages directory. The helper defaults to `resources/js/Pages`;
        // the Laravel React starter ships lowercase `resources/js/pages`,
        // and on a case-sensitive filesystem the helper would otherwise
        // walk a non-existent directory and emit an empty module graph.
        $env = [];
        foreach (['resources/js/Pages', 'resources/js/pages'] as $candidate) {
            if (is_dir($projectRoot.DIRECTORY_SEPARATOR.$candidate)) {
                $env['TIA_VITE_PAGES_DIR'] = $candidate;

                break;
            }
        }

        $process = new Process([$nodeBinary, $helperPath, $projectRoot], $projectRoot, $env);
        $process->setTimeout(self::NODE_TIMEOUT_SECONDS);

        return $process;
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function parseNodeOutput(string $output): ?array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return null;
        }

        $out = [];

        foreach ($decoded as $path => $components) {
            if (! is_string($path)) {
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
                sort($names);
                $out[$path] = $names;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * Stop and discard a leftover warmer subprocess (e.g. on shutdown,
     * or when `build()` resolved from cache without needing the warmer).
     */
    private static function reapWarmer(): void
    {
        $process = self::$warmer;
        self::$warmer = null;
        self::$warmerFingerprint = null;
        self::$warmerProjectRoot = null;
        self::$warmerCacheHit = false;

        if ($process === null) {
            return;
        }

        try {
            if ($process->isRunning()) {
                $process->stop(0.5);
            }
        } catch (\Throwable) {
            // best-effort cleanup
        }
    }

    /**
     * Content fingerprint of every input that can change the Node/Vite
     * module graph: each `resources/js/**` source (path + size + mtime),
     * each `vite.config.*` (path + size + mtime + sha-of-bytes), and
     * the chosen pages-directory casing. Returns null only when no
     * `vite.config.*` exists — i.e. the resolver itself wouldn't run.
     *
     * File inputs use `mtime+size` rather than full content hashes —
     * walking thousands of SFCs and re-hashing them on every flush
     * would defeat the point of the cache. mtime/size collisions on
     * an edited file are theoretically possible but vanishingly rare,
     * and the cost of a rare miss (one extra Node run) is exactly what
     * the cache costs anyway. The vite config itself is small and
     * load-bearing for plugin/alias behaviour, so we hash its bytes
     * outright.
     */
    private static function fingerprint(string $projectRoot): ?string
    {
        if (! self::hasViteConfig($projectRoot)) {
            return null;
        }

        $parts = [];

        foreach (['vite.config.ts', 'vite.config.js', 'vite.config.mjs', 'vite.config.cjs', 'vite.config.mts'] as $name) {
            $path = $projectRoot.DIRECTORY_SEPARATOR.$name;

            if (! is_file($path)) {
                continue;
            }

            $stat = @stat($path);
            $bytes = @file_get_contents($path);

            $parts[] = 'config:'.$name
                .':'.($stat === false ? '0' : (string) $stat['mtime'])
                .':'.($stat === false ? '0' : (string) $stat['size'])
                .':'.($bytes === false ? '' : hash('sha256', $bytes));
        }

        foreach (['Pages', 'pages'] as $dir) {
            if (is_dir($projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.$dir)) {
                $parts[] = 'pagesDir:'.$dir;

                break;
            }
        }

        $jsRoot = $projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js';

        if (is_dir($jsRoot)) {
            $entries = [];

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($jsRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $entries[] = $file->getPathname()
                    .':'.$file->getSize()
                    .':'.$file->getMTime();
            }

            sort($entries);

            $parts[] = 'js:'.hash('sha256', implode("\n", $entries));
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function readCache(string $projectRoot, string $fingerprint): ?array
    {
        $path = self::cachePath($projectRoot);

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        if (($decoded['fingerprint'] ?? null) !== $fingerprint) {
            return null;
        }

        $graph = $decoded['graph'] ?? null;

        if (! is_array($graph)) {
            return null;
        }

        $out = [];

        foreach ($graph as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                continue;
            }

            $names = [];

            foreach ($value as $name) {
                if (is_string($name) && $name !== '') {
                    $names[] = $name;
                }
            }

            $out[$key] = $names;
        }

        return $out;
    }

    /**
     * @param array<string, list<string>> $graph
     */
    private static function writeCache(string $projectRoot, string $fingerprint, array $graph): void
    {
        $path = self::cachePath($projectRoot);
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return;
        }

        $payload = json_encode([
            'fingerprint' => $fingerprint,
            'graph' => $graph,
        ]);

        if ($payload === false) {
            return;
        }

        $tmp = $path.'.tmp.'.bin2hex(random_bytes(4));

        if (@file_put_contents($tmp, $payload) === false) {
            return;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    private static function cachePath(string $projectRoot): string
    {
        return Storage::tempDir($projectRoot).DIRECTORY_SEPARATOR.self::CACHE_FILE;
    }

    private static function hasViteConfig(string $projectRoot): bool
    {
        foreach (['vite.config.ts', 'vite.config.js', 'vite.config.mjs', 'vite.config.cjs', 'vite.config.mts'] as $name) {
            if (is_file($projectRoot.DIRECTORY_SEPARATOR.$name)) {
                return true;
            }
        }

        return false;
    }
}
