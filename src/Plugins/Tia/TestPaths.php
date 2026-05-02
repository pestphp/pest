<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\TestSuite;

/**
 * Resolves the set of project-relative paths that are considered test files,
 * driven by phpunit.xml's <testsuites>. Falls back to the runtime TestSuite
 * configuration when no config file is present.
 *
 * @internal
 */
final readonly class TestPaths
{
    /**
     * @param  list<string>  $directories  Project-relative directory prefixes (no trailing slash).
     * @param  list<string>  $files  Project-relative file paths.
     * @param  list<string>  $suffixes  Filename suffixes (e.g. '.php').
     */
    public function __construct(
        private array $directories,
        private array $files,
        private array $suffixes,
    ) {}

    public static function fromProjectRoot(string $projectRoot): self
    {
        $configPath = self::configPath($projectRoot);

        $directories = [];
        $files = [];
        $suffixes = ['.php'];

        if ($configPath !== null) {
            $xml = @simplexml_load_file($configPath);

            if ($xml !== false) {
                $configDir = dirname($configPath);

                foreach ($xml->xpath('testsuites/testsuite/directory') ?: [] as $node) {
                    $rel = self::toRelative((string) $node, $configDir, $projectRoot);

                    if ($rel !== null) {
                        $directories[] = $rel;
                    }

                    $suffix = (string) ($node['suffix'] ?? '');
                    if ($suffix !== '' && ! in_array($suffix, $suffixes, true)) {
                        $suffixes[] = str_starts_with($suffix, '.') ? $suffix : '.'.$suffix;
                    }
                }

                foreach ($xml->xpath('testsuites/testsuite/file') ?: [] as $node) {
                    $rel = self::toRelative((string) $node, $configDir, $projectRoot);

                    if ($rel !== null) {
                        $files[] = $rel;
                    }
                }
            }
        }

        if ($directories === [] && $files === []) {
            $fallback = self::testSuiteFallback($projectRoot);

            if ($fallback !== null) {
                $directories[] = $fallback;
            }
        }

        return new self(
            array_values(array_unique($directories)),
            array_values(array_unique($files)),
            array_values(array_unique($suffixes)),
        );
    }

    public function isTestFile(string $relativePath): bool
    {
        if (in_array($relativePath, $this->files, true)) {
            return true;
        }

        $matchesSuffix = false;
        foreach ($this->suffixes as $suffix) {
            if (str_ends_with($relativePath, $suffix)) {
                $matchesSuffix = true;

                break;
            }
        }

        if (! $matchesSuffix) {
            return false;
        }

        foreach ($this->directories as $dir) {
            if ($dir === '') {
                continue;
            }
            if (str_starts_with($relativePath, $dir.'/')) {
                return true;
            }
        }

        return false;
    }

    private static function configPath(string $projectRoot): ?string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $name) {
            $candidate = $projectRoot.DIRECTORY_SEPARATOR.$name;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function toRelative(string $value, string $configDir, string $projectRoot): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $isAbsolute = $value[0] === '/' || $value[0] === DIRECTORY_SEPARATOR
            || (strlen($value) >= 2 && $value[1] === ':');

        $combined = $isAbsolute ? $value : $configDir.DIRECTORY_SEPARATOR.$value;

        $real = @realpath($combined);
        $resolved = $real === false ? $combined : $real;

        $resolved = str_replace(DIRECTORY_SEPARATOR, '/', $resolved);
        $root = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($projectRoot, '/\\')).'/';

        if (! str_starts_with($resolved.'/', $root)) {
            return null;
        }

        return rtrim(substr($resolved, strlen($root)), '/');
    }

    private static function testSuiteFallback(string $projectRoot): ?string
    {
        try {
            $testPath = TestSuite::getInstance()->testPath;
        } catch (\Throwable) {
            return null;
        }

        $real = @realpath($testPath);
        $resolved = $real === false ? $testPath : $real;
        $resolved = str_replace(DIRECTORY_SEPARATOR, '/', $resolved);
        $root = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($projectRoot, '/\\')).'/';

        if (! str_starts_with($resolved.'/', $root)) {
            return null;
        }

        return rtrim(substr($resolved, strlen($root)), '/');
    }
}
