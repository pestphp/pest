<?php

declare(strict_types=1);

namespace Pest;

use Pest\PendingCalls\UsesCall;

/**
 * @internal
 */
final class Configuration
{
    private readonly string $filename;

    /**
     * Creates a new configuration instance.
     */
    public function __construct(
        string $filename,
    ) {
        $this->filename = str_ends_with($filename, '/Pest.php') ? dirname($filename) : $filename;
    }

    /**
     * Use the given classes and traits in the given targets.
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
        return new UsesCall(
            $this->filename,
            array_values($classAndTraits)
        );
    }

    /**
     * Depending on where is called, it will add the given groups globally or locally.
     */
    public function group(string ...$groups): UsesCall
    {
        return (new UsesCall($this->filename, []))->group(...$groups);
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

    /**
     * Gets the context configuration.
     */
    public function context(): Configuration\Context
    {
        return new Configuration\Context();
    }
}
