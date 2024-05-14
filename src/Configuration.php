<?php

declare(strict_types=1);

namespace Pest;

use Pest\PendingCalls\UsesCall;
use Pest\Support\Backtrace;

/**
 * @internal
 */
final class Configuration
{
    /**
     * The instance of the configuration.
     */
    private static ?Configuration $instance = null;

    /**
     * Gets the instance of the configuration.
     */
    public static function getInstance(): Configuration
    {
        return self::$instance ??= new Configuration(
            Backtrace::file(),
        );
    }

    /**
     * Creates a new configuration instance.
     */
    private function __construct(
        private readonly string $filename,
    ) {
    }

    /**
     * Gets the configuration of a certain folder.
     */
    public function in(string ...$targets): UsesCall
    {
        return (new UsesCall($this->filename, []))->in(...$targets);
    }

    /**
     * Depending on where is called, it will extend the given classes and traits globally or locally.
     */
    public function extend(string ...$classAndTraits): UsesCall
    {
        return (new UsesCall($this->filename, array_values($classAndTraits)))
            ->in($this->filename)
            ->extend(...$classAndTraits);
    }

    /**
     * Depending on where is called, it will extend the given classes and traits globally or locally.
     */
    public function use(string ...$classAndTraits): UsesCall
    {
        return $this->extend(...$classAndTraits);
    }

    /**
     * Gets the theme configuration.
     */
    public function theme(): Configuration\Theme
    {
        return new Configuration\Theme();
    }
}
