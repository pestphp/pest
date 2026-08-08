<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Cis;

use Pest\Plugins\Tia\Cis\Concerns\ReadsEnvironment;
use Pest\Plugins\Tia\Contracts\Ci;

/**
 * @internal
 */
final readonly class GitHub implements Ci
{
    use ReadsEnvironment;

    public function defaultBranch(): ?string
    {
        $path = $this->environment('GITHUB_EVENT_PATH');

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
}
