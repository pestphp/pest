<?php

declare(strict_types=1);

namespace Pest\Support;

use Composer\InstalledVersions;

final readonly class InstalledPackages
{
    /**
     * @param  array<int, array{root: array{install_path?: string}, versions: array<string, array{dev_requirement?: bool}>}>  $installedSets
     */
    public function __construct(private array $installedSets) {}

    public static function fromComposer(): self
    {
        if (! class_exists(InstalledVersions::class)) {
            return new self([]);
        }

        /** @var array<int, array{root: array{install_path?: string}, versions: array<string, array{dev_requirement?: bool}>}> $installedSets */
        $installedSets = InstalledVersions::getAllRawData();

        return new self($installedSets);
    }

    public static function has(string $package, bool $includeDevRequirements = true): bool
    {
        return self::fromComposer()->contains($package, $includeDevRequirements);
    }

    public function contains(string $package, bool $includeDevRequirements = true): bool
    {
        foreach ($this->installedSets as $installedSet) {
            if (self::isBundledInPhar($installedSet)) {
                continue;
            }

            if (isset($installedSet['versions'][$package])) {
                return $includeDevRequirements || ($installedSet['versions'][$package]['dev_requirement'] ?? false) === false;
            }
        }

        return false;
    }

    /**
     * @param  array{root: array{install_path?: string}}  $installedSet
     */
    private static function isBundledInPhar(array $installedSet): bool
    {
        return str_starts_with($installedSet['root']['install_path'] ?? '', 'phar://');
    }
}
