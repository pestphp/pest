<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\TextUI\Configuration\Registry;
use Throwable;

/**
 * @internal
 */
final class SourceScope
{
    /** @var array<string, bool> */
    private array $containsCache = [];

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
        private readonly array $includes,
        private readonly array $excludes,
    ) {}

    public static function fromProjectRoot(string $projectRoot): self
    {
        $phpunitIncludes = [];
        $phpunitExcludes = [];

        try {
            $source = Registry::get()->source();

            foreach ($source->includeDirectories() as $dir) {
                $phpunitIncludes[] = self::normalise($dir->path());
            }

            foreach ($source->excludeDirectories() as $dir) {
                $phpunitExcludes[] = self::normalise($dir->path());
            }
        } catch (Throwable) {
            // Registry not initialized — fall back to project-root scanning.
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

        return new self($includes, $excludes);
    }

    /**
     * @return list<string> Absolute, normalised paths to testsuite directories and files declared in phpunit.xml.
     */
    public static function testPaths(): array
    {
        try {
            $suites = Registry::get()->testSuite();
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($suites as $suite) {
            foreach ($suite->directories() as $directory) {
                $out[] = self::normalise($directory->path());
            }

            foreach ($suite->files() as $file) {
                $out[] = self::normalise($file->path());
            }
        }

        return array_values(array_unique($out));
    }

    public function contains(string $absoluteFile): bool
    {
        if (isset($this->containsCache[$absoluteFile])) {
            return $this->containsCache[$absoluteFile];
        }

        $real = @realpath($absoluteFile);
        $candidate = $real === false ? $absoluteFile : $real;
        $candidate = self::normalise($candidate);

        foreach ($this->excludes as $excluded) {
            if ($this->startsWithDir($candidate, $excluded)) {
                return $this->containsCache[$absoluteFile] = false;
            }
        }

        foreach ($this->includes as $included) {
            if ($this->startsWithDir($candidate, $included)) {
                return $this->containsCache[$absoluteFile] = true;
            }
        }

        return $this->containsCache[$absoluteFile] = false;
    }

    /**
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
