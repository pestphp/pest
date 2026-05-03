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
    private const int NODE_TIMEOUT_SECONDS = 180;

    private const string CACHE_FILE = 'js-module-graph.cache.json';

    /**
     * @var list<string>
     */
    public const array VITE_CONFIG_NAMES = [
        'vite.config.ts',
        'vite.config.js',
        'vite.config.mjs',
        'vite.config.cjs',
        'vite.config.mts',
    ];

    /**
     * Candidate page directories, in priority order. Must stay in sync with
     * `PAGE_DIR_CANDIDATES` in bin/pest-tia-vite-deps.mjs.
     *
     * @var list<string>
     */
    private const array PAGE_DIR_CANDIDATES = [
        'resources/js/Pages',
        'resources/js/pages',
        'assets/js/Pages',
        'assets/js/pages',
        'assets/Pages',
        'assets/pages',
    ];

    /**
     * @var list<string>
     */
    private const array PAGE_EXTENSIONS = [
        'vue', 'svelte',
        'tsx', 'jsx',
        'ts', 'js',
        'mts', 'cts', 'mjs', 'cjs',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function build(string $projectRoot): array
    {
        $result = self::resolve($projectRoot);

        return $result ?? [];
    }

    /**
     * @return array<string, list<string>>|null
     */
    public static function buildStrict(string $projectRoot): ?array
    {
        return self::resolve($projectRoot);
    }

    public static function isApplicable(string $projectRoot): bool
    {
        if (! self::hasViteConfig($projectRoot)) {
            return false;
        }

        return self::firstExistingPagesDir($projectRoot) !== null;
    }

    private static function firstExistingPagesDir(string $projectRoot): ?string
    {
        foreach (self::PAGE_DIR_CANDIDATES as $rel) {
            $abs = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);

            if (! is_dir($abs)) {
                continue;
            }

            if (self::dirHasPageFile($abs)) {
                return $abs;
            }
        }

        return null;
    }

    private static function dirHasPageFile(string $dir): bool
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
        } catch (\UnexpectedValueException) {
            return false;
        }

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (in_array(strtolower($file->getExtension()), self::PAGE_EXTENSIONS, true)) {
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
                return $cached;
            }
        }

        $process = self::buildNodeProcess($projectRoot);

        if (! $process instanceof Process) {
            return null;
        }

        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $result = self::parseNodeOutput($process->getOutput());

        if ($result !== null && $fingerprint !== null) {
            self::writeCache($projectRoot, $fingerprint, $result);
        }

        return $result;
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

        $process = new Process([$nodeBinary, $helperPath, $projectRoot], $projectRoot);
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

    private static function fingerprint(string $projectRoot): ?string
    {
        $parts = [];

        foreach (self::VITE_CONFIG_NAMES as $name) {
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

        if ($parts === []) {
            return null;
        }

        $override = getenv('TIA_VITE_PAGES_DIR');

        if (is_string($override) && $override !== '') {
            $parts[] = 'pagesDirOverride:'.$override;
        }

        $pagesDir = self::firstExistingPagesDir($projectRoot);

        if ($pagesDir !== null) {
            $parts[] = 'pagesDir:'.str_replace($projectRoot.DIRECTORY_SEPARATOR, '', $pagesDir);
        }

        $jsRoot = $pagesDir !== null ? dirname($pagesDir) : null;

        if ($jsRoot !== null && is_dir($jsRoot)) {
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
            if (! is_string($key)) {
                continue;
            }
            if (! is_array($value)) {
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
     * @param  array<string, list<string>>  $graph
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
        foreach (self::VITE_CONFIG_NAMES as $name) {
            if (is_file($projectRoot.DIRECTORY_SEPARATOR.$name)) {
                return true;
            }
        }

        return false;
    }
}
