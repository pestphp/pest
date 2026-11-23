<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Support\Arr;
use Pest\Support\Git;
use SimpleXMLElement;
use Throwable;

/**
 * @internal
 */
final class ExternalSources
{
    /**
     * @var list<string>
     */
    private const array CONFIGURATION_FLAGS = ['-c', '--configuration'];

    /**
     * @var list<string>
     */
    private const array BOOTSTRAP_FLAGS = ['--bootstrap'];

    /**
     * @var list<string>
     */
    private const array INCLUDE_PATH_FLAGS = ['--include-path'];

    /**
     * @var list<string>
     */
    private const array CONFIGURATION_NAMES = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];

    private const string NO_CONFIGURATION_FLAG = '--no-configuration';

    /**
     * @var array<string, array{roots: array<int, string>, ignored: array<int, string>, unverifiable: array<int, string>}>
     */
    private static array $cache = [];

    private static ?string $workingDirectory = null;

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public static function rootsFor(string $projectRoot, array $arguments = []): array
    {
        return self::resolved($projectRoot, $arguments)['roots'];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public static function unverifiable(string $projectRoot, array $arguments = []): array
    {
        return self::resolved($projectRoot, $arguments)['unverifiable'];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public static function ignoredInputs(string $projectRoot, array $arguments = []): array
    {
        $resolved = self::resolved($projectRoot, $arguments);
        $queries = [];
        $files = [];

        foreach ([...$resolved['roots'], ...$resolved['ignored']] as $root) {
            if ($root === '' || str_ends_with($root, '/')) {
                $queries[] = self::pathspecsUnder($root);
            } elseif (in_array($root, $resolved['ignored'], true)) {
                $files[] = ':(literal)'.$root;
            }
        }

        if ($files !== []) {
            $queries[] = $files;
        }

        if ($queries === []) {
            return [];
        }

        $git = new Git($projectRoot);
        $repositoryRoot = $git->repositoryRoot();

        if ($repositoryRoot === null) {
            return [];
        }

        $repository = new Git($repositoryRoot);
        $projectPrefix = $git->pathPrefix();
        $inputs = [];

        foreach ($queries as $pathspecs) {
            $result = $repository->result(['ls-files', '-z', '--others', '--ignored', '--exclude-standard', '--', ...$pathspecs]);

            if ($result['exitCode'] !== 0) {
                continue;
            }

            foreach (explode("\x00", rtrim($result['output'], "\x00")) as $path) {
                if ($path !== '' && ! str_starts_with($path, $projectPrefix)) {
                    $inputs[$path] = true;
                }
            }
        }

        $inputs = Arr::stringKeys($inputs);
        sort($inputs);

        return $inputs;
    }

    /**
     * @return list<string>
     */
    private static function pathspecsUnder(string $root): array
    {
        $under = self::escapeGlob($root);

        return [
            ':(glob)'.$under.'**',
            ':(exclude,glob)'.$under.'**/.*',
            ':(exclude,glob)'.$under.'**/.*/**',
            ...array_map(
                static fn (string $directory): string => ':(exclude,glob)'.$under.'**/'.$directory.'/**',
                SourceScope::noiseDirectories(),
            ),
        ];
    }

    private static function escapeGlob(string $path): string
    {
        return addcslashes($path, '\\*?[');
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{roots: array<int, string>, ignored: array<int, string>, unverifiable: array<int, string>}
     */
    private static function resolved(string $projectRoot, array $arguments): array
    {
        $configurations = self::configurations($projectRoot, $arguments);

        $key = $projectRoot."\x00".implode("\x00", [...$configurations, ...$arguments]);

        return self::$cache[$key] ??= self::resolve($projectRoot, $configurations, $arguments);
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public static function matching(string $projectRoot, array $files, array $arguments = []): array
    {
        $roots = self::rootsFor($projectRoot, $arguments);

        if ($roots === []) {
            return [];
        }

        $matched = [];

        foreach ($files as $file) {
            foreach ($roots as $root) {
                if (self::covers($root, $file)) {
                    $matched[] = $file;

                    break;
                }
            }
        }

        return $matched;
    }

    public static function flush(): void
    {
        self::$cache = [];
        self::$workingDirectory = null;
    }

    public static function pinWorkingDirectory(string $projectRoot): void
    {
        self::$workingDirectory = getcwd() ?: $projectRoot;
    }

    private static function workingDirectory(string $projectRoot): string
    {
        return self::$workingDirectory ??= (getcwd() ?: $projectRoot);
    }

    private static function covers(string $root, string $file): bool
    {
        if ($root === '') {
            return true;
        }

        $directory = str_ends_with($root, '/');
        $normalised = $directory ? rtrim($root, '/') : $root;

        if ($file === $normalised) {
            return true;
        }

        if ($directory && str_starts_with($file, $root)) {
            return true;
        }

        return str_starts_with($normalised, $file.'/');
    }

    /**
     * @param  array<int, string>  $configurations
     * @param  array<int, string>  $arguments
     * @return array{roots: array<int, string>, ignored: array<int, string>, unverifiable: array<int, string>}
     */
    private static function resolve(string $projectRoot, array $configurations, array $arguments): array
    {
        $git = new Git($projectRoot);
        $repositoryRoot = $git->repositoryRoot();
        $project = self::realpath($projectRoot);

        if ($repositoryRoot === null || $project === null) {
            return ['roots' => [], 'ignored' => [], 'unverifiable' => []];
        }

        $roots = [];
        $unverifiable = [];

        foreach (self::declarations($projectRoot, $configurations, $arguments) as [$resolved, $isDirectory]) {
            if ($resolved === $project || str_starts_with($resolved, $project.'/')) {
                continue;
            }

            if ($resolved === $repositoryRoot) {
                $relative = '';
            } elseif (str_starts_with($resolved, $repositoryRoot.'/')) {
                $relative = substr($resolved, strlen($repositoryRoot) + 1);
            } else {
                $unverifiable[$resolved] = true;

                continue;
            }

            $roots[$isDirectory && $relative !== '' ? $relative.'/' : $relative] = true;
        }

        $roots = $git->pathPrefix() === '' ? [] : Arr::stringKeys($roots);
        $unverifiable = Arr::stringKeys($unverifiable);

        $ignoredRoots = self::ignoredRoots($repositoryRoot, $roots);
        $roots = array_values(array_diff($roots, $ignoredRoots));

        sort($roots);
        sort($ignoredRoots);
        sort($unverifiable);

        return ['roots' => $roots, 'ignored' => $ignoredRoots, 'unverifiable' => $unverifiable];
    }

    /**
     * @param  array<int, string>  $roots
     * @return array<int, string>
     */
    private static function ignoredRoots(string $repositoryRoot, array $roots): array
    {
        static $cache = [];

        $candidates = array_values(array_filter($roots, static fn (string $root): bool => $root !== ''));

        if ($candidates === []) {
            return [];
        }

        $key = $repositoryRoot."\x00".implode("\x00", $candidates);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $result = new Git($repositoryRoot)->result(
            ['check-ignore', '-z', '--stdin'],
            implode("\x00", array_map(static fn (string $root): string => rtrim($root, '/'), $candidates)),
        );

        if ($result['exitCode'] !== 0 && $result['exitCode'] !== 1) {
            return $cache[$key] = [];
        }

        $ignored = [];

        foreach (explode("\x00", rtrim($result['output'], "\x00")) as $path) {
            if ($path === '') {
                continue;
            }

            foreach ($candidates as $root) {
                if (rtrim($root, '/') === $path) {
                    $ignored[] = $root;
                }
            }
        }

        return $cache[$key] = $ignored;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private static function configurations(string $projectRoot, array $arguments): array
    {
        $values = self::argumentValues($arguments, self::CONFIGURATION_FLAGS);
        $fromArguments = $values === [] ? null : $values[count($values) - 1];

        if ($fromArguments !== null) {
            $resolved = self::commandLinePath($projectRoot, $fromArguments);
            $file = $resolved === null ? null : self::configurationFileAt($resolved);

            return $file === null ? [] : [$file];
        }

        if (in_array(self::NO_CONFIGURATION_FLAG, $arguments, true)) {
            return [];
        }

        $file = self::configurationFileIn(self::workingDirectory($projectRoot));

        return $file === null ? [] : [$file];
    }

    private static function configurationFileAt(string $path): ?string
    {
        if (is_dir($path)) {
            return self::configurationFileIn($path);
        }

        return is_file($path) ? $path : null;
    }

    private static function configurationFileIn(string $directory): ?string
    {
        foreach (self::CONFIGURATION_NAMES as $name) {
            $resolved = self::realpath($directory.DIRECTORY_SEPARATOR.$name);

            if ($resolved !== null && is_file($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $arguments
     * @param  list<string>  $flags
     * @return array<int, string>
     */
    private static function argumentValues(array $arguments, array $flags): array
    {
        $values = [];
        $count = count($arguments);

        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];

            foreach ($flags as $flag) {
                if (str_starts_with($argument, $flag.'=')) {
                    $values[] = substr($argument, strlen($flag) + 1);

                    continue 2;
                }

                if ($argument === $flag && isset($arguments[$index + 1])) {
                    $values[] = $arguments[$index + 1];

                    continue 2;
                }

                if (self::isShortFlag($flag) && strlen($argument) > 2 && str_starts_with($argument, $flag)) {
                    $values[] = substr($argument, 2);

                    continue 2;
                }
            }
        }

        return array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
    }

    private static function isShortFlag(string $flag): bool
    {
        return strlen($flag) === 2 && $flag[0] === '-' && $flag[1] !== '-';
    }

    private static function commandLinePath(string $projectRoot, string $path): ?string
    {
        return self::absolutePath(self::workingDirectory($projectRoot), $path);
    }

    /**
     * @param  array<int, string>  $configurations
     * @param  array<int, string>  $arguments
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function declarations(string $projectRoot, array $configurations, array $arguments): array
    {
        $declarations = self::composerDeclarations($projectRoot);

        foreach ($configurations as $configuration) {
            $declarations = [...$declarations, ...self::phpunitDeclarations($configuration)];
        }

        return [...$declarations, ...self::argumentDeclarations($projectRoot, $arguments)];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function argumentDeclarations(string $projectRoot, array $arguments): array
    {
        $declarations = [];

        foreach (self::argumentValues($arguments, self::BOOTSTRAP_FLAGS) as $bootstrap) {
            $resolved = self::commandLinePath($projectRoot, $bootstrap);

            if ($resolved !== null) {
                $declarations[] = [$resolved, false];
            }
        }

        foreach (self::argumentValues($arguments, self::INCLUDE_PATH_FLAGS) as $list) {
            foreach (explode(PATH_SEPARATOR, $list) as $path) {
                if ($path === '') {
                    continue;
                }

                $resolved = self::commandLinePath($projectRoot, $path);

                if ($resolved !== null) {
                    $declarations[] = [$resolved, true];
                }
            }
        }

        return $declarations;
    }

    /**
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function composerDeclarations(string $projectRoot): array
    {
        $manifest = self::decodeJson($projectRoot.DIRECTORY_SEPARATOR.'composer.json');

        if ($manifest === null) {
            return [];
        }

        $declarations = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            $autoload = $manifest[$section] ?? null;

            if (! is_array($autoload)) {
                continue;
            }

            foreach (['psr-4' => true, 'psr-0' => true, 'classmap' => null, 'files' => false] as $kind => $isDirectory) {
                $entries = $autoload[$kind] ?? null;

                if (! is_array($entries)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    foreach (is_array($entry) ? $entry : [$entry] as $path) {
                        if (! is_string($path) || $path === '') {
                            continue;
                        }

                        self::declare($declarations, $projectRoot, $path, $isDirectory);
                    }
                }
            }
        }

        $repositories = $manifest['repositories'] ?? null;

        if (is_array($repositories)) {
            foreach ($repositories as $repository) {
                if (! is_array($repository) || ($repository['type'] ?? null) !== 'path') {
                    continue;
                }

                $url = $repository['url'] ?? null;

                if (is_string($url) && $url !== '') {
                    self::declare($declarations, $projectRoot, $url, true);
                }
            }
        }

        return $declarations;
    }

    /**
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function phpunitDeclarations(string $configuration): array
    {
        $xml = self::parseXml($configuration);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $base = dirname($configuration);
        $declarations = [[str_replace(DIRECTORY_SEPARATOR, '/', $configuration), false]];

        $bootstrap = trim((string) ($xml['bootstrap'] ?? ''));

        if ($bootstrap !== '') {
            self::declare($declarations, $base, $bootstrap, false);
        }

        $sections = ['source/include', 'coverage/include', 'testsuites/testsuite'];

        foreach ($sections as $section) {
            foreach (['directory' => true, 'file' => false] as $node => $isDirectory) {
                foreach ($xml->xpath($section.'/'.$node) ?: [] as $element) {
                    $value = trim((string) $element);

                    if ($value !== '') {
                        self::declare($declarations, $base, $value, $isDirectory);
                    }
                }
            }
        }

        foreach ($xml->xpath('testsuites/testsuite') ?: [] as $suite) {
            $suiteBootstrap = trim((string) ($suite['bootstrap'] ?? ''));

            if ($suiteBootstrap !== '') {
                self::declare($declarations, $base, $suiteBootstrap, false);
            }
        }

        foreach ($xml->xpath('php/includePath') ?: [] as $element) {
            $value = trim((string) $element);

            if ($value !== '') {
                self::declare($declarations, $base, $value, true);
            }
        }

        return $declarations;
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $declarations
     */
    private static function declare(array &$declarations, string $base, string $path, ?bool $isDirectory): void
    {
        $literal = self::beforeWildcard($path);

        if ($literal !== $path) {
            $path = $literal;
            $isDirectory = true;
        }

        if ($path === '') {
            return;
        }

        $resolved = self::absolutePath($base, $path);

        if ($resolved === null) {
            return;
        }

        $isDirectory ??= file_exists($resolved)
            ? is_dir($resolved)
            : ! self::looksLikeFile($resolved);

        $declarations[] = [$resolved, $isDirectory];
    }

    private static function absolutePath(string $base, string $path): ?string
    {
        return self::realpath(self::isAbsolute($path) ? $path : $base.DIRECTORY_SEPARATOR.$path)
            ?? self::lexicalPath(self::realpath($base) ?? $base, $path);
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1;
    }

    private static function beforeWildcard(string $path): string
    {
        $normalised = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        if (! str_contains($normalised, '*') && ! str_contains($normalised, '?')) {
            return $path;
        }

        $segments = [];

        foreach (explode('/', $normalised) as $segment) {
            if (str_contains($segment, '*') || str_contains($segment, '?')) {
                break;
            }

            $segments[] = $segment;
        }

        return rtrim(implode('/', $segments), '/');
    }

    private static function looksLikeFile(string $path): bool
    {
        return pathinfo($path, PATHINFO_EXTENSION) !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function parseXml(string $path): ?SimpleXMLElement
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $xml = @simplexml_load_file($path);
        } catch (Throwable) {
            return null;
        }

        return $xml === false ? null : $xml;
    }

    private static function lexicalPath(string $base, string $relative): ?string
    {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        $joined = self::isAbsolute($relative) ? $relative : str_replace(DIRECTORY_SEPARATOR, '/', $base).'/'.$relative;
        $rootPattern = PHP_OS_FAMILY === 'Windows' ? '#^(?:[a-z]:|//[^/]+/[^/]+)#i' : '#^[a-z]:#i';
        $prefix = preg_match($rootPattern, $joined, $root) === 1 ? $root[0] : '';
        $joined = substr($joined, strlen($prefix));

        $segments = [];

        foreach (explode('/', $joined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment !== '..') {
                $segments[] = $segment;

                continue;
            }

            if ($segments === []) {
                return null;
            }

            array_pop($segments);
        }

        return $prefix.'/'.implode('/', $segments);
    }

    private static function realpath(string $path): ?string
    {
        $resolved = @realpath($path);

        return $resolved === false ? null : rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $resolved), '/');
    }
}
