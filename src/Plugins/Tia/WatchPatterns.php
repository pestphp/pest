<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\WatchDefaults\WatchDefault;
use Pest\TestSuite;

/**
 * @internal
 */
final class WatchPatterns
{
    /**
     * @var array<int, class-string<WatchDefault>>
     */
    private const array DEFAULTS = [
        WatchDefaults\Php::class,
        WatchDefaults\Laravel::class,
        WatchDefaults\Symfony::class,
        WatchDefaults\Livewire::class,
        WatchDefaults\Inertia::class,
        WatchDefaults\Browser::class,
    ];

    /**
     * @var array<string, array<int, string>> glob → list of project-relative test dirs/files
     */
    private array $patterns = [];

    private bool $enabled = false;

    private bool $locally = false;

    private bool $filtered = false;

    public function useDefaults(string $projectRoot): void
    {
        $testPath = TestSuite::getInstance()->testPath;

        foreach (self::DEFAULTS as $class) {
            $default = new $class;

            if (! $default->applicable()) {
                continue;
            }

            foreach ($default->defaults($projectRoot, $testPath) as $glob => $dirs) {
                $this->patterns[$glob] = array_values(array_unique(
                    array_merge($this->patterns[$glob] ?? [], $dirs),
                ));
            }
        }
    }

    /**
     * @param  array<string, string>  $patterns  glob → project-relative test dir/file
     */
    public function add(array $patterns): void
    {
        foreach ($patterns as $glob => $dir) {
            $this->patterns[$glob] = array_values(array_unique(
                array_merge($this->patterns[$glob] ?? [], [$dir]),
            ));
        }
    }

    /**
     * @param  string  $projectRoot  Absolute path.
     * @param  array<int, string>  $changedFiles  Project-relative paths.
     * @return array<int, string> Project-relative test dirs/files.
     */
    public function matchedDirectories(string $projectRoot, array $changedFiles): array
    {
        if ($this->patterns === []) {
            return [];
        }

        $matched = [];

        foreach ($changedFiles as $file) {
            foreach ($this->patterns as $glob => $dirs) {
                if ($this->globMatches($glob, $file)) {
                    foreach ($dirs as $dir) {
                        $matched[$dir] = true;
                    }
                }
            }
        }

        return array_keys($matched);
    }

    /**
     * @param  array<int, string>  $directories  Project-relative dirs/files.
     * @param  array<int, string>  $allTestFiles  Project-relative test files from graph.
     * @return array<int, string>
     */
    public function testsUnderDirectories(array $directories, array $allTestFiles): array
    {
        if ($directories === []) {
            return [];
        }

        $affected = [];

        foreach ($allTestFiles as $testFile) {
            foreach ($directories as $target) {
                if ($testFile === $target) {
                    $affected[] = $testFile;

                    break;
                }

                $prefix = rtrim($target, '/').'/';

                if (str_starts_with($testFile, $prefix)) {
                    $affected[] = $testFile;

                    break;
                }
            }
        }

        return $affected;
    }

    public function markEnabled(): void
    {
        $this->enabled = true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function markLocally(): void
    {
        $this->locally = true;
    }

    public function isLocally(): bool
    {
        return $this->locally;
    }

    public function markFiltered(): void
    {
        $this->filtered = true;
    }

    public function isFiltered(): bool
    {
        return $this->filtered;
    }

    public function reset(): void
    {
        $this->patterns = [];
        $this->enabled = false;
        $this->locally = false;
        $this->filtered = false;
    }

    private function globMatches(string $pattern, string $file): bool
    {
        $pattern = str_replace('\\', '/', $pattern);
        $file = str_replace('\\', '/', $file);

        $regex = '';
        $len = strlen($pattern);
        $i = 0;

        while ($i < $len) {
            $c = $pattern[$i];

            if ($c === '*' && isset($pattern[$i + 1]) && $pattern[$i + 1] === '*') {
                $regex .= '.*';
                $i += 2;

                if (isset($pattern[$i]) && $pattern[$i] === '/') {
                    $i++;
                }
            } elseif ($c === '*') {
                $regex .= '[^/]*';
                $i++;
            } elseif ($c === '?') {
                $regex .= '[^/]';
                $i++;
            } else {
                $regex .= preg_quote($c, '#');
                $i++;
            }
        }

        return (bool) preg_match('#^'.$regex.'$#', $file);
    }
}
