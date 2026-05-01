<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class JsImportParser
{
    private const array PAGE_EXTENSIONS = ['vue', 'tsx', 'jsx', 'svelte'];

    private const array RESOLVABLE_EXTENSIONS = ['vue', 'tsx', 'jsx', 'svelte', 'ts', 'js', 'mjs', 'mts'];

    private const string JS_DIR = 'resources/js';

    /**
     * Walks the project's pages directory (`resources/js/Pages` or its
     * lowercase Laravel-React-starter-kit equivalent `resources/js/pages`)
     * and, for each page, collects its transitive file imports. Returns
     * the inverted graph so callers can look up "what pages depend on
     * this shared file".
     *
     * @return array<string, list<string>>
     */
    public static function parse(string $projectRoot): array
    {
        $jsRoot = $projectRoot.DIRECTORY_SEPARATOR.self::JS_DIR;
        $pagesRoot = null;

        foreach (['resources/js/Pages', 'resources/js/pages'] as $candidate) {
            $abs = $projectRoot.DIRECTORY_SEPARATOR.$candidate;
            if (is_dir($abs)) {
                $pagesRoot = $abs;

                break;
            }
        }

        if ($pagesRoot === null) {
            return [];
        }

        $reverse = [];

        foreach (self::collectPages($pagesRoot) as $pageAbs) {
            $component = self::componentName($pagesRoot, $pageAbs);

            if ($component === null) {
                continue;
            }

            $visited = [];
            self::collectTransitive($pageAbs, $projectRoot, $jsRoot, $visited);

            foreach (array_keys($visited) as $depAbs) {
                if ($depAbs === $pageAbs) {
                    continue;
                }

                $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($depAbs, strlen($projectRoot) + 1));
                $reverse[$rel][$component] = true;
            }
        }

        $out = [];
        foreach ($reverse as $path => $components) {
            $names = array_keys($components);
            sort($names);
            $out[$path] = $names;
        }

        ksort($out);

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function collectPages(string $pagesRoot): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pagesRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower((string) $fileInfo->getExtension());
            if (in_array($ext, self::PAGE_EXTENSIONS, true)) {
                $out[] = $fileInfo->getPathname();
            }
        }

        return $out;
    }

    private static function componentName(string $pagesRoot, string $pageAbs): ?string
    {
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($pageAbs, strlen($pagesRoot) + 1));
        $dot = strrpos($rel, '.');

        if ($dot === false) {
            return null;
        }

        $name = substr($rel, 0, $dot);

        return $name === '' ? null : $name;
    }

    /**
     * @param  array<string, true>  $visited
     */
    private static function collectTransitive(string $fileAbs, string $projectRoot, string $jsRoot, array &$visited): void
    {
        if (isset($visited[$fileAbs])) {
            return;
        }
        $visited[$fileAbs] = true;

        $source = self::loadSource($fileAbs);
        if ($source === null) {
            return;
        }

        foreach (self::extractImports($source) as $spec) {
            $resolved = self::resolveImport($spec, $fileAbs, $jsRoot);
            if ($resolved === null) {
                continue;
            }
            if (! is_file($resolved)) {
                continue;
            }

            self::collectTransitive($resolved, $projectRoot, $jsRoot, $visited);
        }
    }

    /**
     * Loads the importable region of a file. For Vue SFCs, only the
     * `<script>` block is relevant for imports; ignoring the rest
     * avoids false-positive matches inside `<template>` attributes.
     */
    private static function loadSource(string $fileAbs): ?string
    {
        $content = @file_get_contents($fileAbs);
        if ($content === false) {
            return null;
        }

        if (str_ends_with(strtolower($fileAbs), '.vue')) {
            $scripts = [];
            if (preg_match_all('/<script[^>]*>(.*?)<\/script>/si', $content, $m) !== false) {
                foreach ($m[1] as $block) {
                    $scripts[] = $block;
                }
            }

            return implode("\n", $scripts);
        }

        return $content;
    }

    /**
     * Picks out every `import … from '…'` / `import '…'` / `import('…')`
     * target. We strip line comments first so a commented-out import
     * doesn't bloat the dep set.
     *
     * @return list<string>
     */
    private static function extractImports(string $source): array
    {
        $stripped = preg_replace('#//[^\n]*#', '', $source) ?? $source;
        $stripped = preg_replace('#/\*.*?\*/#s', '', $stripped) ?? $stripped;

        $specs = [];

        if (preg_match_all('/\bimport\s+(?:[^\'"()]*?\s+from\s+)?[\'"]([^\'"]+)[\'"]/', $stripped, $matches) !== false) {
            foreach ($matches[1] as $spec) {
                $specs[] = $spec;
            }
        }

        if (preg_match_all('/\bimport\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $matches) !== false) {
            foreach ($matches[1] as $spec) {
                $specs[] = $spec;
            }
        }

        return $specs;
    }

    private static function resolveImport(string $spec, string $importerAbs, string $jsRoot): ?string
    {
        if ($spec === '' || $spec[0] === '.' || $spec[0] === '/') {
            return self::resolveRelative($spec, $importerAbs);
        }

        if (str_starts_with($spec, '@/') || str_starts_with($spec, '~/')) {
            $tail = substr($spec, 2);

            return self::withExtension($jsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $tail));
        }

        // Anything else is either a node_modules package or an
        // unrecognised alias — skip. The watch-pattern fallback
        // handles the safety-net case for non-matched paths.
        return null;
    }

    private static function resolveRelative(string $spec, string $importerAbs): ?string
    {
        if ($spec === '' || $spec[0] === '/') {
            return null;
        }

        $base = dirname($importerAbs);
        $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $spec);

        return self::withExtension($path);
    }

    /**
     * Imports may omit the extension or point at a directory (index.vue,
     * index.ts). Probe the common targets in order.
     */
    private static function withExtension(string $path): ?string
    {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }

        foreach (self::RESOLVABLE_EXTENSIONS as $ext) {
            $candidate = $path.'.'.$ext;
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        foreach (self::RESOLVABLE_EXTENSIONS as $ext) {
            $candidate = $path.DIRECTORY_SEPARATOR.'index.'.$ext;
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }
}
