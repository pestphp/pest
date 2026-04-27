<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Captures environmental inputs that, when changed, may make the TIA graph
 * or its recorded results stale. The fingerprint is split into two buckets:
 *
 *   - **structural** — describes what the graph's *edges* were recorded
 *     against. If any of these drift (`composer.lock`, `composer.json`,
 *     `phpunit.xml{,.dist}`, `vite.config.*`, Pest's factory codegen) the
 *     edges themselves are potentially wrong and the graph must rebuild
 *     from scratch. `tests/TestCase.php` and `tests/Pest.php` are
 *     intentionally NOT here — those are handled by per-test ancestor
 *     linking (`Recorder::linkAncestorFiles`) and the Php watch pattern
 *     respectively, which give precise invalidation rather than a wholesale
 *     rebuild.
 *   - **environmental** — describes the *runtime* the results were captured
 *     on (PHP minor, extension set). Drift here means the edges are still
 *     trustworthy, but the cached per-test results (pass/fail/time) may
 *     not reproduce on this machine. Tia's handler drops the branch's
 *     results + coverage cache and re-runs to freshen them, rather than
 *     re-recording from scratch. Pest's own version is intentionally NOT
 *     here — `composer.lock`'s structural hash already moves whenever the
 *     installed Pest version changes.
 *
 * Legacy flat-shape graphs (schema ≤ 3) are read as structurally stale and
 * rebuilt on first load; the schema bump in the structural bucket takes
 * care of that automatically.
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
    private const int SCHEMA_VERSION = 12;

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
                // `composer.lock` hashed against a *behavioural*
                // subset (per-package version + reference + autoload +
                // extra). Skips per-package install timestamps, dist
                // URLs, support links, descriptions — none of which
                // affect what code runs.
                'composer_lock' => self::composerLockHash($projectRoot),
                'phpunit_xml' => self::hashIfExists($projectRoot.'/phpunit.xml'),
                'phpunit_xml_dist' => self::hashIfExists($projectRoot.'/phpunit.xml.dist'),
                // Pest's generated classes bake the code-generation logic
                // in — if TestCaseFactory changes (new attribute, different
                // method signature, etc.) every previously-recorded edge is
                // stale. Hashing via `ContentHash::of()` so cosmetic edits
                // (comments, formatting) don't drift the fingerprint.
                'pest_factory' => self::contentHashOrNull(__DIR__.'/../../Factories/TestCaseFactory.php'),
                'pest_method_factory' => self::contentHashOrNull(__DIR__.'/../../Factories/TestCaseMethodFactory.php'),
                // `vite.config.*` reshapes the module graph
                // `JsModuleGraph` records at the next `--tia` run; if
                // the config drifts without a rebuild, the stored
                // `$jsFileToComponents` map is silently stale.
                // `viteConfigHash` itself uses `ContentHash::of()` so
                // a comment-only edit to vite.config doesn't rebuild.
                'vite_config' => self::viteConfigHash($projectRoot),
                // `composer.json` hashed against a behavioural subset:
                // autoload(-dev), require(-dev), extra (Laravel
                // package discovery), repositories, minimum-stability,
                // and the platform / allow-plugins entries from
                // `config`. Cosmetic fields (description, keywords,
                // scripts, authors, funding, support) are excluded.
                'composer_json' => self::composerJsonHash($projectRoot),
            ],
            'environmental' => [
                // PHP **minor** only (8.4, not 8.4.19) — CI's resolved patch
                // almost never matches a dev's Herd/Homebrew install, and
                // the patch rarely changes anything test-visible.
                'php_minor' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
                'extensions' => self::extensionsFingerprint($projectRoot),
            ],
        ];
    }

    /**
     * True when the structural buckets match. Drift here means the edges
     * are potentially wrong; caller should discard the graph and rebuild.
     *
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
     * Returns the list of structural field names that drifted between
     * the stored and current fingerprints. Empty list = no drift.
     * Caller uses this to tell the user *why* the graph rebuilt — a
     * generic "graph outdated" message leaves people staring at
     * unrelated diffs.
     *
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
     * Returns a list of field names that drifted between the stored and
     * current environmental fingerprints. Empty list = no drift. Caller
     * uses this to print a human-readable warning and to decide whether
     * per-test results should be dropped (any drift → yes).
     *
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

    /**
     * Returns `$fingerprint[$key]` as an `array<string, mixed>` if it exists
     * and is an array, otherwise empty. Legacy flat-shape fingerprints
     * (schema ≤ 3) return empty here, which makes `structuralMatches` fail
     * and the caller rebuild — the clean migration path.
     *
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

    /**
     * Combined hash of every `vite.config.{ts,js,mjs,cjs,mts}` present
     * at the project root. Most projects have exactly one; we accept
     * any of the five recognised extensions without assuming which
     * the user picked. Returns null when no config file exists —
     * treated as "no Vite project" by the matcher, no drift.
     */
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

    /**
     * Behavioural subset of `composer.json`. Keeps the keys that
     * actually move test outcomes (autoload, require, extra,
     * repositories, minimum-stability, platform / allow-plugins
     * config) and drops cosmetic ones (description, keywords,
     * scripts, authors, funding, homepage, support). Falls back to
     * a raw hash on parse errors so any change still rebuilds.
     */
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

    /**
     * Behavioural subset of `composer.lock`. For every package in
     * `packages` and `packages-dev`, keeps version + dist/source
     * reference (commit SHA — catches dev-branch updates that don't
     * bump the version string) + autoload(-dev) + extra (Laravel
     * package discovery). Drops install timestamps, dist URLs,
     * support links, descriptions, etc. — none of which change what
     * code runs.
     */
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

    /**
     * Recursively sorts associative arrays by key so semantically
     * equivalent JSON produces the same hash regardless of key
     * ordering. Lists (numeric arrays) keep their order — they're
     * meaningful in `repositories`, `autoload.files`, etc.
     */
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

    /**
     * Deterministic hash of the extensions the project actually depends on —
     * the `ext-*` entries in composer.json's `require` / `require-dev`. An
     * incidental extension loaded on the developer's machine (or on CI) but
     * not declared as a dependency can't affect correctness of the test
     * suite, so we ignore it here to keep the drift signal quiet.
     *
     * Declared extensions that aren't currently loaded record as `missing`,
     * which is itself a drift signal worth surfacing.
     */
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

    /**
     * Extension names (without the `ext-` prefix) that appear as keys under
     * `require` or `require-dev` in the project's composer.json. Returns
     * an empty list when composer.json is missing / unreadable / malformed,
     * so the environmental fingerprint stays stable in those cases rather
     * than flapping.
     *
     * @return list<string>
     */
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
