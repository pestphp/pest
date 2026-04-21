<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\Contracts\State;

/**
 * Filesystem-backed implementation of the TIA `State` contract. Each key
 * maps verbatim to a file name under `$rootDir`, so existing `.temp/*.json`
 * layouts are preserved exactly.
 *
 * The root directory is created lazily on first write — callers don't have
 * to pre-provision it, and reads against a missing directory simply return
 * `null` rather than throwing.
 *
 * @internal
 */
final readonly class FileState implements State
{
    /**
     * Configured root. May not exist on disk yet; resolved + created on
     * the first write. Keeping the raw string lets the instance be built
     * before Pest's temp dir has been materialised.
     */
    private string $rootDir;

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

        // Atomic rename — on POSIX filesystems this is a single-step
        // replacement, so concurrent readers never see a half-written file.
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

    /**
     * Absolute path for `$key`. Not part of the interface — used by the
     * coverage merger and similar callers that need direct filesystem
     * access (e.g. `require` on a cached PHP file). Consumers that only
     * deal in bytes should go through `read()` / `write()`.
     */
    public function pathFor(string $key): string
    {
        return $this->rootDir.DIRECTORY_SEPARATOR.$key;
    }

    /**
     * Returns the resolved root if it exists already, otherwise `null`.
     * Used by read-side helpers so they don't eagerly create the directory
     * just to find nothing inside.
     */
    private function resolvedRoot(): ?string
    {
        $resolved = @realpath($this->rootDir);

        return $resolved === false ? null : $resolved;
    }

    /**
     * Creates the root dir on demand. Returns false only when creation
     * fails and the directory still isn't there afterwards.
     */
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
