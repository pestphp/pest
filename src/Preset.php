<?php

declare(strict_types=1);

namespace Pest;

use Closure;
use Pest\Arch\Support\Composer;
use Pest\ArchPresets\AbstractPreset;
use Pest\ArchPresets\Custom;
use Pest\ArchPresets\Laravel;
use Pest\ArchPresets\Php;
use Pest\ArchPresets\Relaxed;
use Pest\ArchPresets\Security;
use Pest\ArchPresets\Strict;
use Pest\Exceptions\InvalidArgumentException;
use Pest\PendingCalls\TestCall;
use stdClass;

/**
 * @internal
 */
final class Preset
{
    /**
     * The application / package base namespaces.
     *
     * @var ?array<int, string>
     */
    private static ?array $baseNamespaces = null;

    /**
     * The custom presets.
     *
     * @var array<string, Closure>
     */
    private static array $customPresets = [];

    /**
     * Creates a new preset instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Uses the Pest php preset and returns the test call instance.
     */
    public function php(): Php
    {
        return $this->executePreset(new Php($this->baseNamespaces()));
    }

    /**
     * Uses the Pest laravel preset and returns the test call instance.
     */
    public function laravel(): Laravel
    {
        return $this->executePreset(new Laravel($this->baseNamespaces()));
    }

    /**
     * Uses the Pest strict preset and returns the test call instance.
     */
    public function strict(): Strict
    {
        return $this->executePreset(new Strict($this->baseNamespaces()));
    }

    /**
     * Uses the Pest security preset and returns the test call instance.
     */
    public function security(): AbstractPreset
    {
        return $this->executePreset(new Security($this->baseNamespaces()));
    }

    /**
     * Uses the Pest relaxed preset and returns the test call instance.
     */
    public function relaxed(): AbstractPreset
    {
        return $this->executePreset(new Relaxed($this->baseNamespaces()));
    }

    /**
     * Uses the Pest custom preset and returns the test call instance.
     *
     * @internal
     */
    public static function custom(string $name, Closure $execute): void
    {
        if (preg_match('/^[a-zA-Z]+$/', $name) === false) {
            throw new InvalidArgumentException('The preset name must only contain words from a-z or A-Z.');
        }

        self::$customPresets[$name] = $execute;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  array<int, mixed>  $arguments
     *
     * @throws InvalidArgumentException
     */
    public function __call(string $name, array $arguments): AbstractPreset
    {
        if (! array_key_exists($name, self::$customPresets)) {
            $availablePresets = [
                ...['php', 'laravel', 'strict', 'security', 'relaxed'],
                ...array_keys(self::$customPresets),
            ];

            throw new InvalidArgumentException(sprintf('The preset [%s] does not exist. The available presets are [%s].', $name, implode(', ', $availablePresets)));
        }

        return $this->executePreset(new Custom($this->baseNamespaces(), $name, self::$customPresets[$name]));
    }

    /**
     * Executes the given preset.
     *
     * @template TPreset of AbstractPreset
     *
     * @param  TPreset  $preset
     * @return TPreset
     */
    private function executePreset(AbstractPreset $preset): AbstractPreset
    {
        $this->baseNamespaces();

        $preset->execute();

        // $this->testCall->testCaseMethod->closure = (function () use ($preset): void {
        //    $preset->flush();
        // })->bindTo(new stdClass);

        return $preset;
    }

    /**
     * Get the base namespaces for the application / package.
     *
     * @return array<int, string>
     */
    private function baseNamespaces(): array
    {
        if (self::$baseNamespaces === null) {
            self::$baseNamespaces = Composer::userNamespaces();
        }

        return self::$baseNamespaces;
    }
}
