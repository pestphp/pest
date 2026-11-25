<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Composer\Autoload\ClassLoader;
use Pest\Plugins\Configuration;
use Pest\Support\Arr;
use Pest\Support\ComposerProject;
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
        return self::ignoredState($projectRoot, $arguments)['inputs'];
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{inputs: array<int, string>, repositories: array<int, string>, failed: array<int, string>, unreadable: array<int, string>}
     */
    public static function ignoredState(string $projectRoot, array $arguments = []): array
    {
        $resolved = self::resolved($projectRoot, $arguments);

        if ($resolved['roots'] === [] && $resolved['ignored'] === []) {
            return ['inputs' => [], 'repositories' => [], 'failed' => [], 'unreadable' => []];
        }

        $git = new Git($projectRoot);
        $repositoryRoot = $git->repositoryRoot();

        if ($repositoryRoot === null) {
            return ['inputs' => [], 'repositories' => [], 'failed' => [], 'unreadable' => []];
        }

        $listing = self::ignoredEntries($repositoryRoot, $git->pathPrefix(), $resolved['roots'], $resolved['ignored']);
        $inputs = [];

        foreach ($listing['entries'] as $path) {
            if (is_file($repositoryRoot.'/'.$path)) {
                $inputs[$path] = true;
            }
        }

        $inputs = Arr::stringKeys($inputs);
        sort($inputs);

        return ['inputs' => $inputs, 'repositories' => $listing['repositories'], 'failed' => $listing['failed'], 'unreadable' => $listing['unreadable']];
    }

    /**
     * @param  array<int, string>  $roots
     * @param  array<int, string>  $ignoredRoots
     * @return array{entries: array<int, string>, repositories: array<int, string>, failed: array<int, string>, unreadable: array<int, string>}
     */
    private static function ignoredEntries(string $repositoryRoot, string $projectPrefix, array $roots, array $ignoredRoots): array
    {
        $queries = [];
        $files = [];

        foreach ([...$roots, ...$ignoredRoots] as $root) {
            if ($root === '' || str_ends_with($root, '/')) {
                $queries[] = [[$root], self::pathspecsUnder($root)];
            } elseif (in_array($root, $ignoredRoots, true)) {
                $files[] = $root;
            }
        }

        if ($files !== []) {
            $queries[] = [$files, array_map(static fn (string $file): string => ':(literal)'.$file, $files)];
        }

        $repository = new Git($repositoryRoot);
        $entries = [];
        $repositories = [];
        $failed = [];
        $unreadable = [];

        foreach ($queries as [$queried, $pathspecs]) {
            $result = $repository->result(['ls-files', '-z', '--others', '--ignored', '--exclude-standard', '--', ...$pathspecs]);

            if ($result['exitCode'] !== 0) {
                array_push($failed, ...$queried);

                continue;
            }

            foreach (explode("\x00", rtrim($result['output'], "\x00")) as $path) {
                if ($path === '' || str_starts_with($path, $projectPrefix)) {
                    continue;
                }

                if (preg_match('//u', $path) !== 1) {
                    $unreadable[$path] = true;

                    continue;
                }

                $entries[$path] = true;

                if (str_ends_with($path, '/') && ! self::isNoiseBelow($queried[0], $path)) {
                    $repositories[rtrim($path, '/')] = true;
                }
            }
        }

        $repositories = Arr::stringKeys($repositories);
        $unreadable = Arr::stringKeys($unreadable);
        sort($repositories);
        sort($failed);
        sort($unreadable);

        return ['entries' => Arr::stringKeys($entries), 'repositories' => $repositories, 'failed' => $failed, 'unreadable' => $unreadable];
    }

    private static function isNoiseBelow(string $root, string $entry): bool
    {
        $below = '/'.substr($entry, strlen($root));

        if (str_contains($below, '/.')) {
            return true;
        }

        return array_any(SourceScope::noiseDirectories(), static fn (string $directory): bool => str_contains($below, '/'.$directory.'/'));
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

        $key = $projectRoot."\x00".implode("\x00", [...array_map(static fn (array $configuration): string => $configuration[0]."\x00".$configuration[1], $configurations), ...$arguments]);

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

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public static function includePaths(string $projectRoot, array $arguments): array
    {
        $values = self::includePathValues($arguments);

        if ($values === []) {
            return [];
        }

        $repositoryRoot = new Git($projectRoot)->repositoryRoot();
        $paths = [];

        foreach ($values as $value) {
            if (! self::isAbsolute($value)) {
                $paths[] = 'relative:'.self::normalisedRelativePath($value);

                continue;
            }

            $resolved = self::commandLinePath($projectRoot, $value) ?? $value;

            if ($repositoryRoot !== null && $resolved === $repositoryRoot) {
                $resolved = '.';
            } elseif ($repositoryRoot !== null && str_starts_with($resolved, $repositoryRoot.'/')) {
                $resolved = substr($resolved, strlen($repositoryRoot) + 1);
            }

            $paths[] = 'absolute:'.$resolved;
        }

        return $paths;
    }

    private static function normalisedRelativePath(string $path): string
    {
        $segments = array_filter(
            explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $path)),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.',
        );

        return $segments === [] ? '.' : implode('/', $segments);
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
     * @param  array<int, array{0: string, 1: string, 2: bool}>  $configurations
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

        $listing = self::ignoredEntries($repositoryRoot, $git->pathPrefix(), $roots, $ignoredRoots);

        foreach ($listing['repositories'] as $nested) {
            $unverifiable[] = $repositoryRoot.'/'.$nested;
        }

        foreach ($listing['failed'] as $root) {
            $unverifiable[] = rtrim($repositoryRoot.'/'.$root, '/');
        }

        foreach ($listing['unreadable'] as $path) {
            $unverifiable[] = rtrim($repositoryRoot.'/'.$path, '/');
        }

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

        $result = new Git($repositoryRoot)->checkIgnore(
            array_map(static fn (string $root): string => rtrim($root, '/'), $candidates),
        );

        if ($result['exitCode'] !== 0 && $result['exitCode'] !== 1) {
            return $cache[$key] = [];
        }

        $ignored = [];

        foreach ($result['ignored'] as $path) {
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
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    private static function configurations(string $projectRoot, array $arguments): array
    {
        $values = self::argumentValues($arguments, self::CONFIGURATION_FLAGS);
        $fromArguments = $values === [] ? null : $values[count($values) - 1];

        if ($fromArguments !== null) {
            $resolved = self::commandLinePath($projectRoot, $fromArguments);
            $file = $resolved === null ? null : self::configurationFileAt($resolved);

            return $file === null ? [] : [[$file, dirname($file), true]];
        }

        $workingDirectory = self::workingDirectory($projectRoot);
        $file = self::configurationFileIn($workingDirectory);

        if ($file === null) {
            return [[Configuration::BASE_PHPUNIT_FILE, $workingDirectory, false]];
        }

        if (in_array(self::NO_CONFIGURATION_FLAG, $arguments, true)) {
            return [];
        }

        return [[$file, dirname($file), true]];
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
     * @param  array<int, array{0: string, 1: string, 2: bool}>  $configurations
     * @param  array<int, string>  $arguments
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function declarations(string $projectRoot, array $configurations, array $arguments): array
    {
        $declarations = self::composerDeclarations($projectRoot);

        foreach ($configurations as [$configuration, $base, $isWatched]) {
            $declarations = [...$declarations, ...self::phpunitDeclarations($projectRoot, $configuration, $base, $isWatched)];
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

        foreach (self::includePathEntries($projectRoot, $arguments) as $resolved) {
            $declarations[] = [$resolved, true];
        }

        return $declarations;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private static function includePathEntries(string $projectRoot, array $arguments): array
    {
        $entries = [];

        foreach (self::includePathValues($arguments) as $path) {
            $resolved = self::commandLinePath($projectRoot, $path);

            if ($resolved !== null) {
                $entries[] = $resolved;
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private static function includePathValues(array $arguments): array
    {
        $values = [];

        foreach (self::argumentValues($arguments, self::INCLUDE_PATH_FLAGS) as $list) {
            foreach (explode(PATH_SEPARATOR, $list) as $path) {
                if ($path !== '') {
                    $values[] = $path;
                }
            }
        }

        return $values;
    }

    /**
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function composerDeclarations(string $projectRoot): array
    {
        $manifest = self::decodeJson(self::composerManifest($projectRoot));

        if ($manifest === null) {
            return [];
        }

        $declarations = [];

        self::declareAutoload($declarations, $projectRoot, $manifest);
        self::declarePathRepositories($declarations, $projectRoot, $manifest);

        foreach (self::installedPathUrls($projectRoot, $manifest) as $url) {
            self::declareLiteral($declarations, $projectRoot, $url, true);
        }

        return $declarations;
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $declarations
     * @param  array<mixed>  $manifest
     */
    private static function declareAutoload(array &$declarations, string $projectRoot, array $manifest): void
    {
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

                        if ($kind === 'classmap') {
                            self::declare($declarations, $projectRoot, $path, $isDirectory, self::braceFlag());
                        } else {
                            self::declareLiteral($declarations, $projectRoot, $path, $isDirectory);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $declarations
     * @param  array<mixed>  $manifest
     */
    private static function declarePathRepositories(array &$declarations, string $projectRoot, array $manifest): void
    {
        $repositories = $manifest['repositories'] ?? null;

        if (is_array($repositories)) {
            foreach ($repositories as $repository) {
                if (! is_array($repository) || ($repository['type'] ?? null) !== 'path') {
                    continue;
                }

                $url = $repository['url'] ?? null;

                if (is_string($url) && $url !== '') {
                    self::declare($declarations, $projectRoot, $url, true, self::braceFlag() | GLOB_ONLYDIR);
                }
            }
        }
    }

    private static function composerManifest(string $projectRoot): string
    {
        $manifest = getenv('COMPOSER');
        $manifest = is_string($manifest) ? trim($manifest) : '';
        $manifest = $manifest !== '' ? $manifest : 'composer.json';

        return self::isAbsolute($manifest) ? $manifest : $projectRoot.DIRECTORY_SEPARATOR.$manifest;
    }

    private static function composerLock(string $projectRoot): string
    {
        $manifest = self::composerManifest($projectRoot);

        return str_ends_with($manifest, '.json') ? substr($manifest, 0, -5).'.lock' : $manifest.'.lock';
    }

    /**
     * @param  array<mixed>  $manifest
     * @return array<int, string>
     */
    private static function installedPathUrls(string $projectRoot, array $manifest): array
    {
        $lock = self::decodeJson(self::composerLock($projectRoot)) ?? [];

        $packages = [
            ...self::listOf($lock['packages'] ?? null),
            ...self::listOf($lock['packages-dev'] ?? null),
        ];

        foreach (self::vendorDirectories($projectRoot, $manifest) as $vendorDirectory) {
            $installed = self::decodeJson($vendorDirectory.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json') ?? [];

            array_push($packages, ...self::listOf(array_key_exists('packages', $installed) ? $installed['packages'] : $installed));
        }

        $urls = [];

        foreach ($packages as $package) {
            $dist = is_array($package) ? ($package['dist'] ?? null) : null;

            if (is_array($dist) && ($dist['type'] ?? null) === 'path' && is_string($dist['url'] ?? null) && $dist['url'] !== '') {
                $urls[$dist['url']] = true;
            }
        }

        return Arr::stringKeys($urls);
    }

    /**
     * @param  array<mixed>  $manifest
     * @return array<int, string>
     */
    private static function vendorDirectories(string $projectRoot, array $manifest): array
    {
        $project = self::realpath($projectRoot);
        $loaded = [];

        foreach (array_keys(ClassLoader::getRegisteredLoaders()) as $vendorDirectory) {
            if ($project !== null && self::realpath(ComposerProject::rootPath($vendorDirectory.DIRECTORY_SEPARATOR.'autoload.php')) === $project) {
                $loaded[] = $vendorDirectory;
            }
        }

        if ($loaded !== []) {
            return $loaded;
        }

        $config = $manifest['config'] ?? null;
        $configured = is_array($config) ? ($config['vendor-dir'] ?? null) : null;
        $directory = is_string($configured) && $configured !== '' ? $configured : 'vendor';

        return [self::isAbsolute($directory) ? $directory : $projectRoot.DIRECTORY_SEPARATOR.$directory];
    }

    /**
     * @return array<int, mixed>
     */
    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return array<int, array{0: string, 1: bool}>
     */
    private static function phpunitDeclarations(string $projectRoot, string $configuration, string $base, bool $isWatched): array
    {
        $xml = self::parseXml($configuration);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $declarations = $isWatched ? [[str_replace(DIRECTORY_SEPARATOR, '/', $configuration), false]] : [];

        $bootstrap = trim((string) ($xml['bootstrap'] ?? ''));

        if ($bootstrap !== '') {
            self::declareLiteral($declarations, $base, $bootstrap, false);
        }

        $sections = ['source/include', 'coverage/include', 'testsuites/testsuite'];

        foreach ($sections as $section) {
            foreach (['directory' => true, 'file' => false] as $node => $isDirectory) {
                foreach ($xml->xpath($section.'/'.$node) ?: [] as $element) {
                    $value = trim((string) $element);

                    if ($value === '') {
                        continue;
                    }

                    if ($isDirectory) {
                        self::declare($declarations, $base, $value, true, GLOB_ONLYDIR, true);
                    } else {
                        self::declareLiteral($declarations, $base, $value, false);
                    }
                }
            }
        }

        foreach ($xml->xpath('testsuites/testsuite') ?: [] as $suite) {
            $suiteBootstrap = trim((string) ($suite['bootstrap'] ?? ''));

            if ($suiteBootstrap !== '') {
                self::declareLiteral($declarations, $base, $suiteBootstrap, false);
            }
        }

        foreach ($xml->xpath('php/includePath') ?: [] as $element) {
            if ((string) $element !== '') {
                self::declareIncludePath($declarations, $projectRoot, $base, trim((string) $element));
            }
        }

        return $declarations;
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $declarations
     */
    private static function declareIncludePath(array &$declarations, string $projectRoot, string $base, string $includePath): void
    {
        foreach (explode(PATH_SEPARATOR, $includePath) as $index => $path) {
            if ($index === 0) {
                self::declareLiteral($declarations, $base, $path === '' ? '.' : $path, true);

                continue;
            }

            if ($path === '') {
                continue;
            }

            $resolved = self::commandLinePath($projectRoot, $path);

            if ($resolved !== null) {
                $declarations[] = [$resolved, true];
            }
        }
    }

    private static function braceFlag(): int
    {
        return defined('GLOB_BRACE') ? GLOB_BRACE : 0;
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $declarations
     */
    private static function declare(array &$declarations, string $base, string $path, ?bool $isDirectory, int $globFlags, bool $globstar = false): void
    {
        $literal = self::beforeWildcard($path, $globFlags);

        if ($literal === $path) {
            self::declareLiteral($declarations, $base, $path, $isDirectory);

            return;
        }

        $pattern = self::isAbsolute($path) ? $path : $base.DIRECTORY_SEPARATOR.$path;
        $matches = $globstar ? self::globstar($pattern) : (glob($pattern, $globFlags) ?: []);

        if ($matches === [] && file_exists($pattern)) {
            self::declareLiteral($declarations, $base, $path, $isDirectory);

            return;
        }

        self::declareLiteral($declarations, $base, $literal, true);

        foreach ($matches as $match) {
            self::declareLiteral($declarations, $base, $match, $isDirectory);
        }
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $declarations
     */
    private static function declareLiteral(array &$declarations, string $base, string $path, ?bool $isDirectory): void
    {
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

    /**
     * @return array<int, string>
     */
    private static function globstar(string $pattern): array
    {
        $position = strpos($pattern, '**');

        if ($position === false) {
            return glob($pattern, GLOB_ONLYDIR) ?: [];
        }

        $root = substr($pattern, 0, $position);
        $rest = substr($pattern, $position + 2);
        $patterns = [$root.ltrim($rest, '/'.DIRECTORY_SEPARATOR)];
        $root .= '*';

        while (($directories = glob($root, GLOB_ONLYDIR)) !== false && $directories !== []) {
            $root .= '/*';

            foreach ($directories as $directory) {
                $patterns[] = $directory.$rest;
            }
        }

        $matches = [];

        foreach ($patterns as $nested) {
            array_push($matches, ...self::globstar($nested));
        }

        return array_values(array_unique($matches));
    }

    private static function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return PHP_OS_FAMILY === 'Windows'
            && (str_starts_with($path, '\\') || preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1);
    }

    private static function beforeWildcard(string $path, int $globFlags): string
    {
        $normalised = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $wildcards = ($globFlags & self::braceFlag()) !== 0 ? '*?[\\{' : '*?[\\';

        if (strpbrk($normalised, $wildcards) === false) {
            return $path;
        }

        $segments = [];

        foreach (explode('/', $normalised) as $segment) {
            if (strpbrk($segment, $wildcards) !== false) {
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

        if (PHP_OS_FAMILY === 'Windows' && preg_match('#^/(?!/)#', $joined) === 1 && preg_match('#^[a-z]:#i', (string) getcwd(), $drive) === 1) {
            $joined = $drive[0].$joined;
        }

        $prefix = PHP_OS_FAMILY === 'Windows' && preg_match('#^(?:[a-z]:|//[^/]+/[^/]+)#i', $joined, $root) === 1 ? $root[0] : '';
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
