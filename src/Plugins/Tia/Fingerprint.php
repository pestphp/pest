<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
final readonly class Fingerprint
{
    private const int SCHEMA_VERSION = 17;

    /**
     * @return array{
     *     structural: array<string, int|string|null>,
     *     environmental: array<string, int|string|null>,
     * }
     */
    public static function compute(string $projectRoot): array
    {
        return [
            'structural' => [
                'schema' => self::SCHEMA_VERSION,
                'composer_lock' => self::composerLockHash($projectRoot),
                'phpunit_xml' => self::trackedHash($projectRoot, 'phpunit.xml'),
                'phpunit_xml_dist' => self::trackedHash($projectRoot, 'phpunit.xml.dist'),
                // 'pest_factory' => self::contentHashOrNull(__DIR__.'/../../Factories/TestCaseFactory.php'),
                // 'pest_method_factory' => self::contentHashOrNull(__DIR__.'/../../Factories/TestCaseMethodFactory.php'),
                'vite_config' => self::viteConfigHash($projectRoot),
                // 'package_json' => self::packageJsonHash($projectRoot),
                'package_lock' => self::packageLockHash($projectRoot),
                'js_config' => self::jsConfigHash($projectRoot),
                // 'composer_json' => self::composerJsonHash($projectRoot),
            ],
            'environmental' => [
                'php_minor' => PHP_MAJOR_VERSION,

                // 'extensions' => self::extensionsFingerprint($projectRoot),
                // 'env_files' => self::envFilesHash($projectRoot),
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
        return self::detectDrift(
            self::structuralOnly($stored),
            self::structuralOnly($current),
            'schema',
        );
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    public static function environmentalDrift(array $stored, array $current): array
    {
        return self::detectDrift(
            self::environmentalOnly($stored),
            self::environmentalOnly($current),
        );
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return list<string>
     */
    private static function detectDrift(array $a, array $b, ?string $skipKey = null): array
    {
        $drifts = [];

        foreach ($a as $key => $value) {
            if ($key === $skipKey) {
                continue;
            }
            if (($b[$key] ?? null) !== $value) {
                $drifts[] = $key;
            }
        }

        foreach ($b as $key => $value) {
            if ($key === $skipKey) {
                continue;
            }
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

        foreach (JsModuleGraph::VITE_CONFIG_NAMES as $name) {
            if (! self::isTrackedByGit($projectRoot, $name)) {
                continue;
            }

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
            if (! self::isTrackedByGit($projectRoot, $name)) {
                continue;
            }

            $hash = self::hashIfExists($projectRoot.'/'.$name);

            if ($hash !== null) {
                $parts[] = $name.':'.$hash;
            }
        }

        return $parts === [] ? null : hash('xxh128', implode("\n", $parts));
    }

    private static function composerLockHash(string $projectRoot): ?string
    {
        return self::trackedHash($projectRoot, 'composer.lock');
    }

    private static function packageLockHash(string $projectRoot): ?string
    {
        $parts = [];

        foreach (['package-lock.json', 'pnpm-lock.yaml', 'yarn.lock', 'bun.lock', 'bun.lockb'] as $name) {
            $hash = self::trackedHash($projectRoot, $name);

            if ($hash !== null) {
                $parts[] = $name.':'.$hash;
            }
        }

        return $parts === [] ? null : hash('xxh128', implode("\n", $parts));
    }

    private static function trackedHash(string $projectRoot, string $relativePath): ?string
    {
        if (! self::isTrackedByGit($projectRoot, $relativePath)) {
            return null;
        }

        return self::hashIfExists($projectRoot.'/'.$relativePath);
    }

    /**
     * Returns true when the file exists and is not gitignored.
     *
     * Gitignored lockfiles (e.g. `package-lock.json` excluded from the repo)
     * regenerate per-machine with OS-specific optional deps, which would
     * otherwise force a fingerprint mismatch on every fetched baseline.
     */
    private static function isTrackedByGit(string $projectRoot, string $relativePath): bool
    {
        if (! is_file($projectRoot.'/'.$relativePath)) {
            return false;
        }

        static $cache = [];

        $key = $projectRoot."\0".$relativePath;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        if (! is_dir($projectRoot.'/.git') && ! is_file($projectRoot.'/.git')) {
            return $cache[$key] = true;
        }

        $finder = (new Finder)
            ->in($projectRoot)
            ->depth('== 0')
            ->name($relativePath)
            ->ignoreVCSIgnored(true);

        return $cache[$key] = $finder->hasResults();
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
}
