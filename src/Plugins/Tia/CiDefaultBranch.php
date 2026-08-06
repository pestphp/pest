<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class CiDefaultBranch
{
    public static function detect(): ?string
    {
        return self::fromGitLab() ?? self::fromGitHubEvent();
    }

    private static function fromGitLab(): ?string
    {
        return self::environment('CI_DEFAULT_BRANCH');
    }

    private static function fromGitHubEvent(): ?string
    {
        $path = self::environment('GITHUB_EVENT_PATH');

        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $payload = json_decode($contents, true);

        if (! is_array($payload) || ! is_array($payload['repository'] ?? null)) {
            return null;
        }

        $branch = $payload['repository']['default_branch'] ?? null;

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
