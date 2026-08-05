<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * The default branch as the CI provider itself reports it.
 *
 * Worth asking before git: a CI checkout is the one place where git knows the
 * least. `actions/checkout` builds the working copy with `git init` plus a
 * single-ref `fetch` rather than a `clone`, so `origin/HEAD` is never set and
 * `init.defaultBranch` — a setting of the runner image, not of the repository —
 * is all git has left to offer. The provider, meanwhile, states the answer
 * outright in the environment it handed us.
 *
 * @internal
 */
final class CiDefaultBranch
{
    /**
     * Advisory, like every other source in the chain: anything unreadable,
     * unparsable, or simply absent means "no answer", never a failure.
     */
    public static function detect(): ?string
    {
        return self::fromGitLab() ?? self::fromGitHubEvent();
    }

    private static function fromGitLab(): ?string
    {
        return self::environment('CI_DEFAULT_BRANCH');
    }

    /**
     * GitHub publishes no default-branch variable, but every repository-scoped
     * event payload carries `repository.default_branch`, and the path to that
     * payload is in the environment.
     */
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
