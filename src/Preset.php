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

/**
 * @internal
 */
final class Preset
{
    /**
     * @var ?array<int, string>
     */
    private static ?array $baseNamespaces = null;

    /**
     * @var array<string, Closure>
     */
    private static array $customPresets = [];

    public function __construct()
    {
        //
    }

    public function php(): Php
    {
        return $this->executePreset(new Php($this->baseNamespaces()));
    }

    public function laravel(): Laravel
    {
        return $this->executePreset(new Laravel($this->baseNamespaces()));
    }

    public function strict(): Strict
    {
        return $this->executePreset(new Strict($this->baseNamespaces()));
    }

    public function security(): AbstractPreset
    {
        return $this->executePreset(new Security($this->baseNamespaces()));
    }

    public function relaxed(): AbstractPreset
    {
        return $this->executePreset(new Relaxed($this->baseNamespaces()));
    }

    /**
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
     * @template TPreset of AbstractPreset
     *
     * @param  TPreset  $preset
     * @return TPreset
     */
    private function executePreset(AbstractPreset $preset): AbstractPreset
    {
        $this->baseNamespaces();

        $preset->execute();

        return $preset;
    }

    /**
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
