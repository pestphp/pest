<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\Contracts\WatchDefault;
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

    private const array VCS_DIRS = ['.git', '.svn', '.hg'];

    /**
     * @var array<string, array<int, string>> raw pattern key → list of project-relative test dirs/files
     */
    private array $patterns = [];

    /**
     * @var array<string, array{include: string, excludes: array<int, string>, allowDotfiles: bool}>
     */
    private array $parsed = [];

    private bool $enabled = false;

    private bool $locally = false;

    private bool $filtered = false;

    private bool $baselined = false;

    public function useDefaults(string $projectRoot): void
    {
        $testPath = TestSuite::getInstance()->testPath;

        foreach (self::DEFAULTS as $class) {
            $default = new $class;

            if (! $default->applicable()) {
                continue;
            }

            foreach ($default->defaults($projectRoot, $testPath) as $key => $dirs) {
                $this->patterns[$key] = array_values(array_unique(
                    array_merge($this->patterns[$key] ?? [], $dirs),
                ));
            }
        }
    }

    /**
     * @param  array<string, string>  $patterns  pattern key → project-relative test dir/file
     */
    public function add(array $patterns): void
    {
        foreach ($patterns as $key => $dir) {
            $this->patterns[$key] = array_values(array_unique(
                array_merge($this->patterns[$key] ?? [], [$dir]),
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
            foreach ($this->patterns as $key => $dirs) {
                if (! $this->keyMatches($key, $file)) {
                    continue;
                }

                foreach ($dirs as $dir) {
                    $matched[$dir] = true;
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

    public function markBaselined(): void
    {
        $this->baselined = true;
    }

    public function isBaselined(): bool
    {
        return $this->baselined;
    }

    public function reset(): void
    {
        $this->patterns = [];
        $this->parsed = [];
        $this->enabled = false;
        $this->locally = false;
        $this->filtered = false;
        $this->baselined = false;
    }

    private function keyMatches(string $key, string $file): bool
    {
        $rule = $this->parse($key);

        if (! $this->globMatches($rule['include'], $file)) {
            return false;
        }

        $file = str_replace('\\', '/', $file);

        if ($this->touchesVcs($file)) {
            return false;
        }

        if (! $rule['allowDotfiles'] && $this->touchesDotfile($file)) {
            return false;
        }

        foreach ($rule['excludes'] as $exclude) {
            if ($this->excludeMatches($exclude, $file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{include: string, excludes: array<int, string>, allowDotfiles: bool}
     */
    private function parse(string $key): array
    {
        if (isset($this->parsed[$key])) {
            return $this->parsed[$key];
        }

        $tokens = preg_split('/\s+/', trim($key)) ?: [];

        $include = '';
        $excludes = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if ($token[0] === '!') {
                $excludes[] = substr($token, 1);

                continue;
            }

            if ($include === '') {
                $include = $token;
            }
        }

        return $this->parsed[$key] = [
            'include' => $include,
            'excludes' => $excludes,
            'allowDotfiles' => $this->patternTargetsDotfiles($include),
        ];
    }

    private function patternTargetsDotfiles(string $pattern): bool
    {
        foreach (explode('/', str_replace('\\', '/', $pattern)) as $segment) {
            if ($segment !== '' && $segment[0] === '.') {
                return true;
            }
        }

        return false;
    }

    private function touchesVcs(string $file): bool
    {
        foreach (explode('/', $file) as $segment) {
            if (in_array($segment, self::VCS_DIRS, true)) {
                return true;
            }
        }

        return false;
    }

    private function touchesDotfile(string $file): bool
    {
        foreach (explode('/', $file) as $segment) {
            if ($segment !== '' && $segment[0] === '.') {
                return true;
            }
        }

        return false;
    }

    private function excludeMatches(string $exclude, string $file): bool
    {
        $pattern = str_contains($exclude, '/') ? $exclude : '**/'.$exclude;

        if ($this->globMatches($pattern, $file)) {
            return true;
        }

        return $this->globMatches($exclude, basename($file));
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
