<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final readonly class SourceScope
{
    /**
     * Top-level directory names always treated as out-of-scope. These
     * mirror what a Laravel app considers "not source": dependencies,
     * editor metadata, framework artefacts, the TIA state itself.
     */
    private const array TOP_LEVEL_NOISE = [
        'vendor',
        'node_modules',
        '.git',
        '.idea',
        '.vscode',
        '.github',
        '.pest',
        '.phpunit.cache',
        '.cache',
    ];

    /**
     * Nested paths (relative to project root) that must be excluded
     * even when their top-level parent is in scope. Laravel writes
     * compiled views, route caches, and packaged manifests here on
     * every framework boot — instrumenting them would burn cycles
     * and create noisy edges.
     */
    private const array NESTED_NOISE = [
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    /**
     * @param  list<string>  $includes  Absolute, normalised directory paths.
     * @param  list<string>  $excludes  Absolute, normalised directory paths.
     */
    public function __construct(
        private array $includes,
        private array $excludes,
    ) {}

    public static function fromProjectRoot(string $projectRoot): self
    {
        $configPath = self::configPath($projectRoot);

        $phpunitIncludes = [];
        $phpunitExcludes = [];

        if ($configPath !== null) {
            $xml = @simplexml_load_file($configPath);

            if ($xml !== false) {
                $configDir = dirname($configPath);
                $phpunitIncludes = self::extractDirectories($xml, 'source/include/directory', $configDir);
                $phpunitExcludes = self::extractDirectories($xml, 'source/exclude/directory', $configDir);
            }
        }

        $rootIncludes = self::topLevelProjectDirs($projectRoot);

        $includes = array_values(array_unique([...$phpunitIncludes, ...$rootIncludes]));
        $excludes = array_values(array_unique([
            ...$phpunitExcludes,
            ...self::nestedNoiseDirs($projectRoot),
        ]));

        if ($includes === []) {
            $includes = [self::normalise($projectRoot)];
        }

        return new self($projectRoot, $includes, $excludes);
    }

    /**
     * True when the absolute file path is inside an `<include>`
     * directory and not under any exclude. Symlinks are resolved on
     * the input so a `realpath()`'d coverage entry still matches a
     * config that pointed at the unresolved tree.
     */
    public function contains(string $absoluteFile): bool
    {
        $real = @realpath($absoluteFile);
        $candidate = $real === false ? $absoluteFile : $real;
        $candidate = self::normalise($candidate);

        foreach ($this->excludes as $excluded) {
            if ($this->startsWithDir($candidate, $excluded)) {
                return false;
            }
        }

        foreach ($this->includes as $included) {
            if ($this->startsWithDir($candidate, $included)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Project-relative directories the resolver considers in scope.
     * Useful for setting `pcov.directory` (a single common ancestor)
     * or `\pcov\collect()`'s file filter.
     *
     * @return list<string>
     */
    public function includes(): array
    {
        return $this->includes;
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

    /**
     * @return list<string>
     */
    private static function extractDirectories(\SimpleXMLElement $xml, string $xpath, string $configDir): array
    {
        $nodes = $xml->xpath($xpath);

        if (! is_array($nodes)) {
            return [];
        }

        $out = [];

        foreach ($nodes as $node) {
            $value = trim((string) $node);

            if ($value === '') {
                continue;
            }

            $absolute = self::resolveRelative($value, $configDir);

            if ($absolute === null) {
                continue;
            }

            $out[] = $absolute;
        }

        return array_values(array_unique($out));
    }

    /**
     * Every top-level directory under `$projectRoot` except those on
     * the noise list. Hidden entries (dotdirs) are skipped unless
     * they're explicitly project source — keeping `.git/`, `.idea/`
     * etc. out without an explicit allowlist.
     *
     * @return list<string>
     */
    private static function topLevelProjectDirs(string $projectRoot): array
    {
        $entries = @scandir($projectRoot);

        if ($entries === false) {
            return [];
        }

        $out = [];

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            if (in_array($entry, self::TOP_LEVEL_NOISE, true)) {
                continue;
            }

            if ($entry !== '' && $entry[0] === '.') {
                continue;
            }

            $abs = $projectRoot.DIRECTORY_SEPARATOR.$entry;

            if (! is_dir($abs)) {
                continue;
            }

            $out[] = self::normalise(@realpath($abs) ?: $abs);
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function nestedNoiseDirs(string $projectRoot): array
    {
        $out = [];

        foreach (self::NESTED_NOISE as $relative) {
            $abs = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $out[] = self::normalise(@realpath($abs) ?: $abs);
        }

        return $out;
    }

    private static function resolveRelative(string $path, string $configDir): string
    {
        $isAbsolute = $path !== '' && ($path[0] === DIRECTORY_SEPARATOR || $path[0] === '/'
            || (strlen($path) >= 2 && $path[1] === ':'));

        $combined = $isAbsolute ? $path : $configDir.DIRECTORY_SEPARATOR.$path;

        $real = @realpath($combined);

        if ($real === false) {
            // Directory may not exist yet (e.g. generated source) — keep
            // the unresolved path so a future file under it still matches.
            return self::normalise($combined);
        }

        return self::normalise($real);
    }

    private static function normalise(string $path): string
    {
        return rtrim($path, '/\\');
    }

    private function startsWithDir(string $candidate, string $dir): bool
    {
        if ($candidate === $dir) {
            return true;
        }

        return str_starts_with($candidate, $dir.DIRECTORY_SEPARATOR);
    }
}
