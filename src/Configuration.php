<?php

declare(strict_types=1);

namespace Pest;

use Pest\PendingCalls\BeforeEachCall;
use Pest\PendingCalls\UsesCall;

/**
 * @internal
 *
 * @mixin UsesCall
 */
final readonly class Configuration
{
    /**
     * The filename of the configuration.
     */
    private string $filename;

    /**
     * Creates a new configuration instance.
     */
    public function __construct(
        string $filename,
    ) {
        $this->filename = str_ends_with($filename, DIRECTORY_SEPARATOR.'Pest.php') ? dirname($filename) : $filename;
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
     * Depending on where is called, it will extend the given classes and traits globally or locally.
     */
    public function extends(string ...$classAndTraits): UsesCall
    {
        return $this->extend(...$classAndTraits);
    }

    /**
     * Depending on where is called, it will add the given groups globally or locally.
     */
    public function group(string ...$groups): UsesCall
    {
        return (new UsesCall($this->filename, []))->group(...$groups);
    }

    /**
     * Marks all tests in the current file to be run exclusively.
     */
    public function only(): void
    {
        (new BeforeEachCall(TestSuite::getInstance(), $this->filename))->only();
    }

    /**
     * Depending on where is called, it will extend the given classes and traits globally or locally.
     */
    public function use(string ...$classAndTraits): UsesCall
    {
        return $this->extend(...$classAndTraits);
    }

    /**
     * Depending on where is called, it will extend the given classes and traits globally or locally.
     */
    public function uses(string ...$classAndTraits): UsesCall
    {
        return $this->extends(...$classAndTraits);
    }

    /**
     * Gets the printer configuration.
     */
    public function printer(): Configuration\Printer
    {
        return new Configuration\Printer;
    }

    /**
     * Gets the presets configuration.
     */
    public function presets(): Configuration\Presets
    {
        return new Configuration\Presets;
    }

    /**
     * Gets the project configuration.
     */
    public function project(): Configuration\Project
    {
        return Configuration\Project::getInstance();
    }

    /**
     * Gets the browser configuration.
     */
    public function browser(): Browser\Configuration
    {
        return new Browser\Configuration;
    }

    /**
     * Proxies calls to the uses method.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->uses()->$name(...$arguments); // @phpstan-ignore-line
    }
}
