<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Builds a reverse dependency map for the project's JS sources under
 * `resources/js/**` — for every source file, the list of Inertia page
 * components that transitively import it.
 *
 * Tries two resolvers in order:
 *
 *   1. **Node helper** (`bin/pest-tia-vite-deps.mjs`). Spins up a
 *      headless Vite server in middleware mode, walks Vite's own
 *      module graph for each page entry, and outputs JSON. Uses the
 *      project's real `vite.config.*`, so aliases, plugins, and SFC
 *      transformers produce the same graph Vite itself would use.
 *
 *   2. **PHP fallback** (`JsImportParser`). Regex-scans ES imports
 *      and resolves `@/` / `~/` aliases manually. Strictly less
 *      precise — anything it can't resolve is skipped, leaving the
 *      caller to fall back to the broad watch pattern. Only kicks in
 *      when the Node helper is unusable (no Node on PATH, no Vite
 *      installed, vite.config fails to load).
 *
 * Callers invoke this at record time; results are persisted into the
 * graph so replay never re-runs the resolver. On stale-map detection
 * the callers decide whether to rebuild.
 *
 * @internal
 */
final class JsModuleGraph
{
    private const int NODE_TIMEOUT_SECONDS = 25;

    /**
     * @return array<string, list<string>> project-relative source path → sorted list of page component names
     */
    public static function build(string $projectRoot): array
    {
        $viaNode = self::tryNodeHelper($projectRoot);

        if ($viaNode !== null) {
            return $viaNode;
        }

        return JsImportParser::parse($projectRoot);
    }

    /**
     * True when the project looks like a Vite + Node project we can
     * ask for a module graph. Gate for callers that want to skip the
     * resolver entirely on non-Vite apps.
     */
    public static function isApplicable(string $projectRoot): bool
    {
        return self::hasViteConfig($projectRoot) && is_dir($projectRoot.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'Pages');
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function tryNodeHelper(string $projectRoot): ?array
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
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($process->getOutput(), true);

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
