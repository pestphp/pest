<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class BootstrappedFiles
{
    /**
     * @var array<string, true>
     */
    private static array $files = [];

    public static function add(string $filename): void
    {
        self::$files[self::key($filename)] = true;
    }

    public static function contains(string $filename): bool
    {
        return isset(self::$files[self::key($filename)]);
    }

    private static function key(string $filename): string
    {
        $real = realpath($filename);

        return str_replace('\\', '/', $real !== false ? $real : $filename);
    }
}
