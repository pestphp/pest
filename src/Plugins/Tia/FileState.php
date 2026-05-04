<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\Contracts\State;

/**
 * @internal
 */
final class FileState implements State
{
    private readonly string $rootDir;

    private ?string $resolvedRoot = null;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
    }

    public function read(string $key): ?string
    {
        $path = $this->pathFor($key);

        if (! is_file($path)) {
            return null;
        }

        $bytes = @file_get_contents($path);

        return $bytes === false ? null : $bytes;
    }

    public function write(string $key, string $content): bool
    {
        if (! $this->ensureRoot()) {
            return false;
        }

        $path = $this->pathFor($key);
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (@file_put_contents($tmp, $content) === false) {
            return false;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $path = $this->pathFor($key);

        if (! is_file($path)) {
            return true;
        }

        return @unlink($path);
    }

    public function exists(string $key): bool
    {
        return is_file($this->pathFor($key));
    }

    public function keysWithPrefix(string $prefix): array
    {
        $root = $this->resolvedRoot();

        if ($root === null) {
            return [];
        }

        $pattern = $root.DIRECTORY_SEPARATOR.$prefix.'*';
        $matches = glob($pattern);

        if ($matches === false) {
            return [];
        }

        $keys = [];

        foreach ($matches as $path) {
            $keys[] = basename($path);
        }

        return $keys;
    }

    public function pathFor(string $key): string
    {
        return $this->rootDir.DIRECTORY_SEPARATOR.$key;
    }

    private function resolvedRoot(): ?string
    {
        if ($this->resolvedRoot !== null) {
            return $this->resolvedRoot;
        }

        $resolved = @realpath($this->rootDir);

        if ($resolved === false) {
            return null;
        }

        return $this->resolvedRoot = $resolved;
    }

    private function ensureRoot(): bool
    {
        if (is_dir($this->rootDir)) {
            return true;
        }

        if (@mkdir($this->rootDir, 0755, true)) {
            return true;
        }

        return is_dir($this->rootDir);
    }
}
