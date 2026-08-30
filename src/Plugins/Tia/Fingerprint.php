<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\Contracts\Lockfile;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
final readonly class Fingerprint
{
    private const int SCHEMA_VERSION = 18;

    /**
     * @var array<int, class-string<Lockfile>>
     */
    private const array LOCKFILES = [
        Lockfiles\PackageLock::class,
    ];

    /**
     * @param  array<int, string>  $arguments
     * @return array{
     *     structural: array<string, int|string|null>,
     *     environmental: array<string, int|string|null>,
     * }
     */
    public static function compute(string $projectRoot, array $arguments = []): array
    {
        return [
            'structural' => [
                'schema' => self::SCHEMA_VERSION,
                'composer_lock' => self::composerLockHash($projectRoot),
                'phpunit_xml' => self::phpunitConfigurationHash($projectRoot, $arguments),
                'vite_config' => self::viteConfigHash($projectRoot),
                'package_lock' => self::packageLockHash($projectRoot),
                'js_config' => self::jsConfigHash($projectRoot),
            ],
            'environmental' => [
                'php_minor' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,

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

    /**
     * @param  array<int, string>  $arguments
     */
    private static function phpunitConfigurationHash(string $projectRoot, array $arguments): ?string
    {
        foreach (self::configurationCandidates($projectRoot, $arguments) as $candidate) {
            $hash = self::hashIfExists($candidate);

            if ($hash !== null) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return list<string>
     */
    private static function configurationCandidates(string $projectRoot, array $arguments): array
    {
        $option = self::configurationOption($arguments);

        $path = $option === null ? $projectRoot : realpath($option);

        if ($path === false) {
            return [];
        }

        if (is_file($path)) {
            return [$path];
        }

        return [
            $path.DIRECTORY_SEPARATOR.'phpunit.xml',
            $path.DIRECTORY_SEPARATOR.'phpunit.xml.dist',
        ];
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private static function configurationOption(array $arguments): ?string
    {
        $arguments = array_values($arguments);

        foreach ($arguments as $index => $argument) {
            if ($argument === '-c' || $argument === '--configuration') {
                return $arguments[$index + 1] ?? null;
            }

            if (str_starts_with($argument, '--configuration=')) {
                return substr($argument, strlen('--configuration='));
            }

            if (str_starts_with($argument, '-c')) {
                return substr($argument, 2);
            }
        }

        return null;
    }

    private static function composerLockHash(string $projectRoot): ?string
    {
        return self::trackedHash($projectRoot, 'composer.lock');
    }

    private static function packageLockHash(string $projectRoot): ?string
    {
        $parts = [];

        foreach (['package-lock.json', 'pnpm-lock.yaml', 'yarn.lock', 'bun.lock', 'bun.lockb'] as $name) {
            if (! self::isTrackedByGit($projectRoot, $name)) {
                continue;
            }

            $hash = self::lockfileHash($projectRoot.'/'.$name, $name);

            if ($hash !== null) {
                $parts[] = $name.':'.$hash;
            }
        }

        return $parts === [] ? null : hash('xxh128', implode("\n", $parts));
    }

    private static function lockfileHash(string $path, string $name): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        foreach (self::LOCKFILES as $class) {
            $handler = new $class;

            if (! $handler->applies($name)) {
                continue;
            }

            $fingerprint = $handler->fingerprint($contents);

            if ($fingerprint !== null) {
                return $fingerprint;
            }

            break;
        }

        return hash('xxh128', $contents);
    }

    private static function trackedHash(string $projectRoot, string $relativePath): ?string
    {
        if (! self::isTrackedByGit($projectRoot, $relativePath)) {
            return null;
        }

        return self::hashIfExists($projectRoot.'/'.$relativePath);
    }

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
