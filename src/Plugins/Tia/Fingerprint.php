<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Captures environmental inputs that, when changed, make the TIA graph stale.
 *
 * Any drift in PHP version, Composer lock, or Pest/PHPUnit config can change
 * what a test actually exercises, so the graph must be rebuilt in those cases.
 *
 * @internal
 */
final readonly class Fingerprint
{
    // Bump this whenever the set of inputs or the hash algorithm changes, so
    // older graphs are invalidated automatically.
    private const int SCHEMA_VERSION = 2;

    /**
     * @return array<string, int|string|null>
     */
    public static function compute(string $projectRoot): array
    {
        return [
            'schema' => self::SCHEMA_VERSION,
            'php' => PHP_VERSION,
            'pest' => self::readPestVersion($projectRoot),
            'composer_lock' => self::hashIfExists($projectRoot.'/composer.lock'),
            'phpunit_xml' => self::hashIfExists($projectRoot.'/phpunit.xml'),
            'phpunit_xml_dist' => self::hashIfExists($projectRoot.'/phpunit.xml.dist'),
            'pest_php' => self::hashIfExists($projectRoot.'/tests/Pest.php'),
            // Pest's generated classes bake the code-generation logic in — if
            // TestCaseFactory changes (new attribute, different method
            // signature, etc.) every previously-recorded edge is stale.
            // Hashing the factory sources makes path-repo / dev-main installs
            // automatically rebuild their graphs when Pest itself is edited.
            'pest_factory' => self::hashIfExists(__DIR__.'/../../Factories/TestCaseFactory.php'),
            'pest_method_factory' => self::hashIfExists(__DIR__.'/../../Factories/TestCaseMethodFactory.php'),
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function matches(array $a, array $b): bool
    {
        ksort($a);
        ksort($b);

        return $a === $b;
    }

    private static function hashIfExists(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $hash = @hash_file('xxh128', $path);

        return $hash === false ? null : $hash;
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
