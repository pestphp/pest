<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Captures environmental inputs that, when changed, may make the TIA graph
 * or its recorded results stale. The fingerprint is split into two buckets:
 *
 *   - **structural** — describes what the graph's *edges* were recorded
 *     against. If any of these drift (`composer.lock`, `tests/Pest.php`,
 *     Pest's factory codegen, etc.) the edges themselves are potentially
 *     wrong and the graph must rebuild from scratch.
 *   - **environmental** — describes the *runtime* the results were captured
 *     on (PHP minor, extension set, Pest version). Drift here means the
 *     edges are still trustworthy, but the cached per-test results (pass/
 *     fail/time) may not reproduce on this machine. Tia's handler drops the
 *     branch's results + coverage cache and re-runs to freshen them, rather
 *     than re-recording from scratch.
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
    private const int SCHEMA_VERSION = 10;

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
                'composer_lock' => self::hashIfExists($projectRoot.'/composer.lock'),
                'phpunit_xml' => self::hashIfExists($projectRoot.'/phpunit.xml'),
                'phpunit_xml_dist' => self::hashIfExists($projectRoot.'/phpunit.xml.dist'),
                'pest_php' => self::hashIfExists($projectRoot.'/tests/Pest.php'),
                // Pest's generated classes bake the code-generation logic
                // in — if TestCaseFactory changes (new attribute, different
                // method signature, etc.) every previously-recorded edge is
                // stale. Hashing the factory sources makes path-repo /
                // dev-main installs automatically rebuild their graphs when
                // Pest itself is edited.
                'pest_factory' => self::hashIfExists(__DIR__.'/../../Factories/TestCaseFactory.php'),
                'pest_method_factory' => self::hashIfExists(__DIR__.'/../../Factories/TestCaseMethodFactory.php'),
            ],
            'environmental' => [
                // PHP **minor** only (8.4, not 8.4.19) — CI's resolved patch
                // almost never matches a dev's Herd/Homebrew install, and
                // the patch rarely changes anything test-visible.
                'php_minor' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
                'extensions' => self::extensionsFingerprint($projectRoot),
                'pest' => self::readPestVersion($projectRoot),
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

    private static function readPestVersion(string $projectRoot): string
    {
        $installed = $projectRoot.'/vendor/composer/installed.json';

        if (! is_file($installed)) {
            return 'unknown';
        }

        $raw = @file_get_contents($installed);

        if ($raw === false) {
            return 'unknown';
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['packages']) || ! is_array($data['packages'])) {
            return 'unknown';
        }

        foreach ($data['packages'] as $package) {
            if (is_array($package) && ($package['name'] ?? null) === 'pestphp/pest') {
                return (string) ($package['version'] ?? 'unknown');
            }
        }

        return 'unknown';
    }
}
