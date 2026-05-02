<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final readonly class Fingerprint
{
    private const int SCHEMA_VERSION = 15;

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

    private static function composerLockHash(string $projectRoot): ?string
    {
        return self::hashIfExists($projectRoot.'/composer.lock');
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
}
