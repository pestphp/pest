<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class Storage
{
    public static function tempDir(string $projectRoot): string
    {
        $home = self::homeDir();

        if ($home === null) {
            return $projectRoot
                .DIRECTORY_SEPARATOR.'.pest'
                .DIRECTORY_SEPARATOR.'tia';
        }

        return $home
            .DIRECTORY_SEPARATOR.'.pest'
            .DIRECTORY_SEPARATOR.'tia'
            .DIRECTORY_SEPARATOR.self::projectKey($projectRoot);
    }

    public static function purge(string $projectRoot): void
    {
        $dir = self::tempDir($projectRoot);

        if (! is_dir($dir)) {
            return;
        }

        self::removeRecursive($dir);
    }

    private static function removeRecursive(string $dir): void
    {
        $entries = @scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path) && ! is_link($path)) {
                self::removeRecursive($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    private static function homeDir(): ?string
    {
        foreach (['HOME', 'USERPROFILE'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '' && is_dir($value)) {
                return rtrim($value, '/\\');
            }
        }

        return null;
    }

    /**
     *      `git@github.com:foo/bar.git`, `ssh://git@github.com/foo/bar`
     */
    private static function projectKey(string $projectRoot): string
    {
        $origin = self::originIdentity($projectRoot);

        $realpath = @realpath($projectRoot);
        $input = $origin ?? ($realpath === false ? $projectRoot : $realpath);

        $hash = substr(hash('sha256', $input), 0, 16);
        $slug = self::slug(basename($projectRoot));

        return $slug === '' ? $hash : $slug.'-'.$hash;
    }

    private static function originIdentity(string $projectRoot): ?string
    {
        $url = self::rawOriginUrl($projectRoot);

        if ($url === null) {
            return null;
        }

        // git@host:org/repo(.git)
        if (preg_match('#^[\w.-]+@([\w.-]+):([\w./-]+?)(?:\.git)?/?$#', $url, $m) === 1) {
            return strtolower($m[1].'/'.$m[2]);
        }

        // scheme://[user@]host[:port]/org/repo(.git)  — https, ssh, git, file
        if (preg_match('#^[a-z]+://(?:[^@/]+@)?([^/:]+)(?::\d+)?/([\w./-]+?)(?:\.git)?/?$#i', $url, $m) === 1) {
            return strtolower($m[1].'/'.$m[2]);
        }

        return strtolower($url);
    }

    private static function rawOriginUrl(string $projectRoot): ?string
    {
        $config = $projectRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config';

        if (! is_file($config)) {
            return null;
        }

        $raw = @file_get_contents($config);

        if ($raw === false) {
            return null;
        }

        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $raw, $match) === 1) {
            return trim($match[1]);
        }

        return null;
    }

    private static function slug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
