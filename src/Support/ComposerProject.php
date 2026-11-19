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

        /** @var array{root: array{install_path: string}} $installed */
        $installed = require $metadataPath;

        return realpath($installed['root']['install_path']) ?: $rootPath;
    }
}
