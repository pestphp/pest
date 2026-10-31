<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Plugins\Environment;

/**
 * @internal
 */
final class Ci
{
    /**
     * @var list<string>
     */
    private const array ENVIRONMENT_VARIABLES = [
        'CI',
        'GITHUB_ACTIONS',
        'GITLAB_CI',
        'CIRCLECI',
        'TRAVIS',
        'APPVEYOR',
        'BITBUCKET_BUILD_NUMBER',
        'BUILDKITE',
        'TEAMCITY_VERSION',
        'JENKINS_URL',
        'SYSTEM_COLLECTIONURI',
        'CI_NAME',
        'TASKCLUSTER_ROOT_URL',
        'DRONE',
        'WERCKER',
        'NEVERCODE',
        'SEMAPHORE',
        'NETLIFY',
        'NOW_BUILDER',
    ];

    public static function isRunning(): bool
    {
        if (Environment::name() === Environment::CI) {
            return true;
        }

        return array_any(self::ENVIRONMENT_VARIABLES, fn (string $env): bool => getenv($env) !== false);
    }
}
