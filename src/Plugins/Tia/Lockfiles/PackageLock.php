<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Lockfiles;

use Pest\Plugins\Tia\Contracts\Lockfile;

/**
 * @internal
 */
final readonly class PackageLock implements Lockfile
{
    public function applies(string $filename): bool
    {
        return $filename === 'package-lock.json';
    }

    public function fingerprint(string $contents): ?string
    {
        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['packages']) || ! is_array($data['packages'])) {
            return null;
        }

        $packages = $data['packages'];

        $platformPaths = [];

        foreach ($packages as $path => $meta) {
            if (is_string($path) && is_array($meta) && $this->isPlatformSpecific($meta)) {
                $platformPaths[] = $path;
            }
        }

        $entries = [];

        foreach ($packages as $path => $meta) {
            if (! is_string($path)) {
                continue;
            }
            if (! is_array($meta)) {
                continue;
            }
            if ($this->isPlatformSpecific($meta)) {
                continue;
            }
            if ($this->isBundledUnderPlatform($path, $platformPaths)) {
                continue;
            }
            $version = $meta['version'] ?? null;

            $entries[$path] = is_string($version) ? $version : '';
        }

        ksort($entries);

        $encoded = json_encode($entries, JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : hash('xxh128', $encoded);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function isPlatformSpecific(array $meta): bool
    {
        return isset($meta['os']) || isset($meta['cpu']) || isset($meta['libc']);
    }

    /**
     * @param  list<string>  $platformPaths
     */
    private function isBundledUnderPlatform(string $path, array $platformPaths): bool
    {
        return array_any($platformPaths, fn (string $platformPath): bool => str_starts_with($path, $platformPath.'/node_modules/'));
    }
}
