<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Two-bucket fingerprint for TIA staleness detection.
 *
 * - **structural**: inputs whose drift means graph *edges* may be wrong → full rebuild.
 *   `tests/TestCase.php` and `tests/Pest.php` are intentionally absent; they're covered by
 *   `Recorder::linkAncestorFiles` and the watch pattern, giving precise per-test invalidation.
 * - **environmental**: runtime inputs (PHP version, extensions, env files) whose drift means
 *   edges are still valid but cached results may not reproduce → drop results and re-run.
 *   Pest's own version is absent; `composer.lock` moves whenever Pest is upgraded.
 *
 * @internal
 */
final readonly class Fingerprint
{
    // Bump this whenever the set of inputs or the hash algorithm changes,
    // so older graphs are invalidated automatically.
    //
    //   v5: ChangedFiles now hashes via `ContentHash` (normalises PHP
    //       tokens + Blade whitespace/comments) instead of raw bytes.
    //       Old graphs' run-tree hashes are incompatible and must be
    //       rebuilt.
    //   v6: Graph gained per-test table edges (`$testTables`) powering
    //       surgical migration invalidation. Worker partial shape
    //       changed to `{files, tables}`. Old graphs have no table
    //       coverage, which would leave every DB test invalidated by
    //       any migration change — force a rebuild so the new edges
    //       are populated.
    //   v7: Graph gained per-test Inertia page-component edges
    //       (`$testInertiaComponents`) for surgical page-file
    //       invalidation. Worker partial now includes an `inertia`
    //       section. Old graphs have no component edges; without a
    //       rebuild Vue/React page edits would fall through to the
    //       broad watch pattern even when precise matching could have
    //       worked.
    //   v8: Graph gained `$jsFileToComponents` — reverse dependency
    //       map computed at record time from Vite's module graph (or
    //       the PHP fallback) so shared components / layouts /
    //       composables invalidate the specific pages they're used
    //       by, not every browser test.
    //   v9: `ContentHash` now normalises JS/TS/Vue/Svelte comments +
    //       whitespace. Old graphs' run-tree hashes for those files
    //       were raw-byte; mixing formats would flag every JS file as
    //       changed on first run.
    //   v10: `vite.config.*` hashed into the structural bucket. A
    //        Vite config change reshapes the module dependency graph
    //        that `JsModuleGraph` records; without a graph rebuild
    //        the stored `$jsFileToComponents` map silently goes stale.
    //   v11: `composer.json` added (autoload-dev / extra discovery
    //        changes). `tests/TestCase.php` and `tests/Pest.php` are
    //        intentionally NOT fingerprinted — they're handled by the
    //        watch pattern + `Recorder::linkAncestorFiles` reflection
    //        walk, which gives precise per-test invalidation rather
    //        than a wholesale rebuild that trashes the entire graph.
    //   v12: PHP/JS structural inputs (pest_factory*, vite.config.*)
    //        now hash via `ContentHash::of()` so cosmetic comment +
    //        whitespace edits don't fire rebuilds. composer.json and
    //        composer.lock hash a behavioural subset — description,
    //        keywords, scripts, authors, install timestamps, dist
    //        URLs etc. no longer drift the structural fingerprint.
    //   v13: Environment files (`.env`, `.env.testing`, local variants)
    //        are included in the environmental bucket. They are commonly
    //        git-ignored, so watch patterns alone cannot reliably notice
    //        edits; a drift drops cached results and re-executes the suite.
    //   v14: Node/Vite resolver inputs (`package*.json`, `tsconfig.*`,
    //        `jsconfig.*`) are included in the structural bucket. They can
    //        reshape the persisted JS module graph without touching
    //        `vite.config.*` itself.
    private const int SCHEMA_VERSION = 14;

    /**
     * @return array{
     *     structural: array<string, int|string|null>,
     *     environmental: array<string, string|null>,
     * }
     */
    public static function compute(string $projectRoot): array
    {
        return [
            'structural' => [
                'schema' => self::SCHEMA_VERSION,
                'composer_lock' => self::composerLockHash($projectRoot),
                'phpunit_xml' => self::hashIfExists($projectRoot.'/phpunit.xml'),
                'phpunit_xml_dist' => self::hashIfExists($projectRoot.'/phpunit.xml.dist'),
                'pest_factory' => self::contentHashOrNull(__DIR__.'/../../Factories/TestCaseFactory.php'),
                'pest_method_factory' => self::contentHashOrNull(__DIR__.'/../../Factories/TestCaseMethodFactory.php'),
                'vite_config' => self::viteConfigHash($projectRoot),
                'package_json' => self::packageJsonHash($projectRoot),
                'package_lock' => self::packageLockHash($projectRoot),
                'js_config' => self::jsConfigHash($projectRoot),
                'composer_json' => self::composerJsonHash($projectRoot),
            ],
            'environmental' => [
                // Minor only (8.4, not 8.4.19) — CI's patch rarely matches dev installs.
                'php_minor' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
                'extensions' => self::extensionsFingerprint($projectRoot),
                'env_files' => self::envFilesHash($projectRoot),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function structuralMatches(array $a, array $b): bool
    {
        $aStructural = self::structuralOnly($a);
        $bStructural = self::structuralOnly($b);

        ksort($aStructural);
        ksort($bStructural);

        return $aStructural === $bStructural;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    public static function structuralDrift(array $stored, array $current): array
    {
        $a = self::structuralOnly($stored);
        $b = self::structuralOnly($current);

        $drifts = [];

        foreach ($a as $key => $value) {
            if ($key === 'schema') {
                continue;
            }
            if (($b[$key] ?? null) !== $value) {
                $drifts[] = $key;
            }
        }

        foreach ($b as $key => $value) {
            if ($key === 'schema') {
                continue;
            }
            if (! array_key_exists($key, $a) && $value !== null) {
                $drifts[] = $key;
            }
        }

        return array_values(array_unique($drifts));
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    public static function environmentalDrift(array $stored, array $current): array
    {
        $a = self::environmentalOnly($stored);
        $b = self::environmentalOnly($current);

        $drifts = [];

        foreach ($a as $key => $value) {
            if (($b[$key] ?? null) !== $value) {
                $drifts[] = $key;
            }
        }

        foreach ($b as $key => $value) {
            if (! array_key_exists($key, $a) && $value !== null) {
                $drifts[] = $key;
            }
        }

        return array_values(array_unique($drifts));
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     * @return array<string, mixed>
     */
    private static function structuralOnly(array $fingerprint): array
    {
        return self::bucket($fingerprint, 'structural');
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     * @return array<string, mixed>
     */
    private static function environmentalOnly(array $fingerprint): array
    {
        return self::bucket($fingerprint, 'environmental');
    }

    // Legacy flat-shape fingerprints (schema ≤ 3) return empty, causing structuralMatches to fail → rebuild.
    /**
     * @param  array<string, mixed>  $fingerprint
     * @return array<string, mixed>
     */
    private static function bucket(array $fingerprint, string $key): array
    {
        $raw = $fingerprint[$key] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $normalised = [];

        foreach ($raw as $k => $v) {
            if (is_string($k)) {
                $normalised[$k] = $v;
            }
        }

        return $normalised;
    }

    private static function viteConfigHash(string $projectRoot): ?string
    {
        $parts = [];

        foreach (['vite.config.ts', 'vite.config.js', 'vite.config.mjs', 'vite.config.cjs', 'vite.config.mts'] as $name) {
            $hash = self::contentHashOrNull($projectRoot.'/'.$name);

            if ($hash !== null) {
                $parts[] = $name.':'.$hash;
            }
        }

        return $parts === [] ? null : hash('xxh128', implode("\n", $parts));
    }

    private static function jsConfigHash(string $projectRoot): ?string
    {
        $parts = [];

        foreach (['tsconfig.json', 'tsconfig.app.json', 'jsconfig.json'] as $name) {
            $hash = self::hashIfExists($projectRoot.'/'.$name);

            if ($hash !== null) {
                $parts[] = $name.':'.$hash;
            }
        }

        return $parts === [] ? null : hash('xxh128', implode("\n", $parts));
    }

    private static function packageJsonHash(string $projectRoot): ?string
    {
        $path = $projectRoot.'/package.json';

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $hash = @hash_file('xxh128', $path);

            return $hash === false ? null : $hash;
        }

        $relevant = [
            'type' => $data['type'] ?? null,
            'packageManager' => $data['packageManager'] ?? null,
            'dependencies' => $data['dependencies'] ?? null,
            'devDependencies' => $data['devDependencies'] ?? null,
            'optionalDependencies' => $data['optionalDependencies'] ?? null,
            'peerDependencies' => $data['peerDependencies'] ?? null,
            'overrides' => $data['overrides'] ?? null,
            'resolutions' => $data['resolutions'] ?? null,
            'imports' => $data['imports'] ?? null,
            'exports' => $data['exports'] ?? null,
            'browser' => $data['browser'] ?? null,
        ];

        self::sortRecursively($relevant);

        $json = json_encode($relevant);

        return $json === false ? null : hash('xxh128', $json);
    }

    private static function packageLockHash(string $projectRoot): ?string
    {
        $parts = [];

        foreach (['package-lock.json', 'pnpm-lock.yaml', 'yarn.lock', 'bun.lock', 'bun.lockb'] as $name) {
            $hash = self::hashIfExists($projectRoot.'/'.$name);

            if ($hash !== null) {
                $parts[] = $name.':'.$hash;
            }
        }

        return $parts === [] ? null : hash('xxh128', implode("\n", $parts));
    }

    private static function envFilesHash(string $projectRoot): ?string
    {
        $paths = [
            $projectRoot.'/.env',
            $projectRoot.'/.env.testing',
            $projectRoot.'/.env.local',
        ];

        $localVariants = glob($projectRoot.'/.env.*.local');

        if (is_array($localVariants)) {
            foreach ($localVariants as $path) {
                $paths[] = $path;
            }
        }

        $parts = [];
        $seen = [];

        foreach ($paths as $path) {
            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;

            if (! is_file($path)) {
                continue;
            }

            $contents = @file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $parts[] = basename($path).':'.hash('xxh128', $contents);
        }

        if ($parts === []) {
            return null;
        }

        sort($parts);

        return hash('xxh128', implode("\n", $parts));
    }

    private static function composerJsonHash(string $projectRoot): ?string
    {
        $path = $projectRoot.'/composer.json';

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $hash = @hash_file('xxh128', $path);

            return $hash === false ? null : $hash;
        }

        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        $relevantConfig = array_intersect_key($config, [
            'platform' => true,
            'allow-plugins' => true,
        ]);

        $relevant = [
            'autoload' => $data['autoload'] ?? null,
            'autoload-dev' => $data['autoload-dev'] ?? null,
            'require' => $data['require'] ?? null,
            'require-dev' => $data['require-dev'] ?? null,
            'extra' => $data['extra'] ?? null,
            'repositories' => $data['repositories'] ?? null,
            'minimum-stability' => $data['minimum-stability'] ?? null,
            'prefer-stable' => $data['prefer-stable'] ?? null,
            'config' => $relevantConfig === [] ? null : $relevantConfig,
        ];

        self::sortRecursively($relevant);

        $json = json_encode($relevant);

        return $json === false ? null : hash('xxh128', $json);
    }

    private static function composerLockHash(string $projectRoot): ?string
    {
        $path = $projectRoot.'/composer.lock';

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $hash = @hash_file('xxh128', $path);

            return $hash === false ? null : $hash;
        }

        $relevant = [
            'platform' => $data['platform'] ?? null,
            'platform-dev' => $data['platform-dev'] ?? null,
        ];

        foreach (['packages', 'packages-dev'] as $section) {
            if (! isset($data[$section])) {
                continue;
            }
            if (! is_array($data[$section])) {
                continue;
            }
            $packages = [];

            foreach ($data[$section] as $package) {
                if (! is_array($package)) {
                    continue;
                }

                $name = $package['name'] ?? null;

                if (! is_string($name)) {
                    continue;
                }

                $packages[$name] = [
                    'version' => $package['version'] ?? null,
                    'reference' => self::lockReference($package),
                    'autoload' => $package['autoload'] ?? null,
                    'autoload-dev' => $package['autoload-dev'] ?? null,
                    'extra' => $package['extra'] ?? null,
                ];
            }

            ksort($packages);
            $relevant[$section] = $packages;
        }

        self::sortRecursively($relevant);

        $json = json_encode($relevant);

        return $json === false ? null : hash('xxh128', $json);
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private static function lockReference(array $package): ?string
    {
        $dist = is_array($package['dist'] ?? null) ? $package['dist'] : [];
        $source = is_array($package['source'] ?? null) ? $package['source'] : [];

        $reference = $dist['reference'] ?? $source['reference'] ?? null;

        return is_string($reference) ? $reference : null;
    }

    private static function sortRecursively(mixed &$value): void
    {
        if (! is_array($value)) {
            return;
        }

        $isAssoc = ! array_is_list($value);

        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as &$child) {
            self::sortRecursively($child);
        }
    }

    private static function contentHashOrNull(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $hash = ContentHash::of($path);

        return $hash === false ? null : $hash;
    }

    private static function hashIfExists(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $hash = @hash_file('xxh128', $path);

        return $hash === false ? null : $hash;
    }

    // Only hashes `ext-*` entries declared in composer.json — incidental extensions loaded on the
    // machine but not declared can't affect suite correctness, so they're excluded to reduce noise.
    private static function extensionsFingerprint(string $projectRoot): string
    {
        $extensions = self::declaredExtensions($projectRoot);

        if ($extensions === []) {
            return hash('xxh128', '');
        }

        sort($extensions);

        $parts = [];

        foreach ($extensions as $name) {
            $version = phpversion($name);
            $parts[] = $name.'@'.($version === false ? 'missing' : $version);
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /** @return list<string> */
    private static function declaredExtensions(string $projectRoot): array
    {
        $path = $projectRoot.'/composer.json';

        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return [];
        }

        $extensions = [];

        foreach (['require', 'require-dev'] as $section) {
            $packages = $data[$section] ?? null;

            if (! is_array($packages)) {
                continue;
            }

            foreach (array_keys($packages) as $package) {
                if (is_string($package) && str_starts_with($package, 'ext-')) {
                    $extensions[] = substr($package, 4);
                }
            }
        }

        return array_values(array_unique($extensions));
    }
}
