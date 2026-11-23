<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\TextUI\CliArguments\Builder;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use Throwable;

/**
 * @internal
 */
final readonly class ConfigurationFile
{
    private const string SUPPRESSED = 'none';

    private const array DEFAULT_NAMES = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];

    private const array VALUE_OPTIONS = ['--configuration', '--bootstrap', '--include-path'];

    /**
     * @param  list<string>|null  $includePaths
     */
    private function __construct(
        private ?string $path,
        private bool $suppressed,
        private ?string $bootstrap = null,
        private ?array $includePaths = null,
    ) {}

    /**
     * @param  array<int, string>  $arguments
     */
    public static function fromArguments(array $arguments, ?string $generatedPath = null): self
    {
        try {
            $configuration = new Builder(EventFacade::emitter())->fromParameters(self::configurationParameters($arguments));
        } catch (Throwable) {
            return self::projectDefault();
        }

        $bootstrap = $configuration->hasBootstrap() ? self::resolved($configuration->bootstrap()) : null;
        $includePaths = $configuration->hasIncludePath()
            ? array_map(self::resolved(...), explode(PATH_SEPARATOR, $configuration->includePath()))
            : null;

        if (! $configuration->hasConfigurationFile() && ! $configuration->useDefaultConfiguration()) {
            return new self(null, true, $bootstrap, $includePaths);
        }

        $file = new XmlConfigurationFileFinder()->find($configuration);
        $path = $file === false ? false : realpath($file);

        if ($path !== false && $generatedPath !== null && $path === realpath($generatedPath)) {
            return new self(null, false, $bootstrap, $includePaths);
        }

        return new self($path === false ? null : $path, false, $bootstrap, $includePaths);
    }

    public static function at(string $path): self
    {
        return new self($path, false);
    }

    public static function projectDefault(): self
    {
        return new self(null, false);
    }

    public function bootstrapFingerprint(string $projectRoot): ?string
    {
        return $this->bootstrap === null ? null : $this->relativeTo($projectRoot, $this->bootstrap);
    }

    public function includePathFingerprint(string $projectRoot): ?string
    {
        if ($this->includePaths === null) {
            return null;
        }

        $paths = array_map(
            fn (string $path): string => $this->relativeTo($projectRoot, $path),
            $this->includePaths,
        );

        return implode("\0", $paths);
    }

    public function fingerprint(string $projectRoot): ?string
    {
        if ($this->suppressed) {
            return self::SUPPRESSED;
        }

        $path = $this->path ?? $this->defaultIn($projectRoot);

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $hash = @hash_file('xxh128', $path);

        return $hash === false ? null : $this->relativeTo($projectRoot, $path).':'.$hash;
    }

    private function defaultIn(string $projectRoot): ?string
    {
        foreach (self::DEFAULT_NAMES as $name) {
            if (is_file($projectRoot.DIRECTORY_SEPARATOR.$name)) {
                return $projectRoot.DIRECTORY_SEPARATOR.$name;
            }
        }

        return null;
    }

    private function relativeTo(string $projectRoot, string $path): string
    {
        $root = rtrim($this->withForwardSlashes(realpath($projectRoot) ?: $projectRoot), '/').'/';
        $resolvedPath = $this->withForwardSlashes(self::resolved($path));

        if ($resolvedPath.'/' === $root) {
            return '.';
        }

        return str_starts_with($resolvedPath, $root) ? substr($resolvedPath, strlen($root)) : $resolvedPath;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return list<string>
     */
    private static function configurationParameters(array $arguments): array
    {
        $arguments = array_values($arguments);
        $parameters = [];

        foreach ($arguments as $index => $argument) {
            if ($argument === '--no-configuration') {
                $parameters[] = $argument;

                continue;
            }

            $option = self::withLongConfigurationOption($argument);

            foreach (self::VALUE_OPTIONS as $name) {
                if ($option === $name && isset($arguments[$index + 1])) {
                    $parameters[] = $name;
                    $parameters[] = $arguments[$index + 1];
                } elseif (str_starts_with($option, $name.'=')) {
                    $parameters[] = $name;
                    $parameters[] = substr($option, strlen($name.'='));
                }
            }
        }

        return $parameters;
    }

    private static function withLongConfigurationOption(string $argument): string
    {
        if ($argument === '-c') {
            return '--configuration';
        }

        if (str_starts_with($argument, '-c')) {
            return '--configuration='.substr($argument, 2);
        }

        return $argument;
    }

    private function withForwardSlashes(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('\\', '/', $path) : $path;
    }

    private static function resolved(string $path): string
    {
        $real = $path === '' ? false : realpath($path);

        return $real === false ? $path : $real;
    }
}
