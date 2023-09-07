<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;
use Pest\TestSuite;

final class Snapshot implements HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * @var array<string, callable>
     */
    private static array $macros = [];

    /**
     * @var array<string, callable>
     */
    private static array $interceptors = [];

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! $this->hasArgument('--update-snapshots', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--parallel', $arguments)) {
            throw new InvalidOption('The [--update-snapshots] option is not supported when running in parallel.');
        }

        TestSuite::getInstance()->snapshots->flush();

        return $this->popArgument('--update-snapshots', $arguments);
    }

    public static function intercept(string $class, callable $callable): void
    {
        self::$interceptors[$class] = $callable;
    }

    /**
     * @return array<string, callable>
     */
    public static function getInterceptors(): array
    {
        return self::$interceptors;
    }

    public static function macro(string $key, callable $callable): void
    {
        self::$macros[$key] = $callable;
    }

    public static function disableMacro(string $key): void
    {
        unset(self::$macros[$key]);
    }

    /**
     * @return array<string, callable>
     */
    public static function getMacros(): array
    {
        return self::$macros;
    }
}
