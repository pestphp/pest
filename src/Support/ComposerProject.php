<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class ComposerProject
{
    public static function rootPath(string $autoloadPath): string
    {
        $vendorPath = dirname($autoloadPath);
        $vendorPath = realpath($vendorPath) ?: $vendorPath;
        $rootPath = dirname($vendorPath);
        $metadataPath = $vendorPath.'/composer/installed.php';

        if (! is_file($metadataPath)) {
            return $rootPath;
        }

        $installed = require $metadataPath;
        $root = is_array($installed) ? ($installed['root'] ?? null) : null;
        $installPath = is_array($root) ? ($root['install_path'] ?? null) : null;

        if (! is_string($installPath)) {
            return $rootPath;
        }

        return realpath($installPath) ?: $rootPath;
    }
}
