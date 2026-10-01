<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

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

    private function __construct(
        private ?string $path,
        private bool $suppressed,
    ) {}

    /**
     * @param  array<int, string>  $arguments
     */
    public static function fromArguments(array $arguments): self
    {
        try {
            $configuration = new Builder()->fromParameters(self::configurationParameters($arguments));
        } catch (Throwable) {
            return self::projectDefault();
        }

        if (! $configuration->hasConfigurationFile() && ! $configuration->useDefaultConfiguration()) {
            return new self(null, true);
        }

        $file = new XmlConfigurationFileFinder()->find($configuration);
        $path = $file === false ? false : realpath($file);

        return new self($path === false ? null : $path, false);
    }

    public static function at(string $path): self
    {
        return new self($path, false);
    }

    public static function projectDefault(): self
    {
        return new self(null, false);
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
        $root = rtrim(str_replace('\\', '/', realpath($projectRoot) ?: $projectRoot), '/').'/';
        $file = str_replace('\\', '/', realpath($path) ?: $path);

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
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
            } elseif ($argument === '--configuration' || $argument === '-c') {
                if (isset($arguments[$index + 1])) {
                    $parameters[] = '--configuration';
                    $parameters[] = $arguments[$index + 1];
                }
            } elseif (str_starts_with($argument, '--configuration=')) {
                $parameters[] = '--configuration';
                $parameters[] = substr($argument, strlen('--configuration='));
            } elseif (str_starts_with($argument, '-c') && ! str_starts_with($argument, '--')) {
                $parameters[] = '--configuration';
                $parameters[] = substr($argument, 2);
            }
        }

        return $parameters;
    }
}
