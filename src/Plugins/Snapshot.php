<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\TestSuite;

/**
 * @internal
 */
final class Snapshot implements HandlesArguments
{
    use Concerns\HandleArguments;

    public static bool $updateSnapshots = false;

    /**
     * @var list<string>
     */
    private const array CI_ENVIRONMENT_VARIABLES = [
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

    public static function shouldCreateMissingSnapshots(): bool
    {
        return self::$updateSnapshots || ! self::runningOnCI();
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (Parallel::isWorker() && Parallel::getGlobal('UPDATE_SNAPSHOTS') === true) {
            self::$updateSnapshots = true;

            return $arguments;
        }

        if (! $this->hasArgument('--update-snapshots', $arguments)) {
            return $arguments;
        }

        self::$updateSnapshots = true;

        if ($this->isFullRun($arguments)) {
            TestSuite::getInstance()->snapshots->flush();
        }

        if ($this->hasArgument('--parallel', $arguments) || $this->hasArgument('-p', $arguments)) {
            Parallel::setGlobal('UPDATE_SNAPSHOTS', true);
        }

        return $this->popArgument('--update-snapshots', $arguments);
    }

    /**
     * @var list<string>
     */
    private const array FLAGS_WITH_VALUES = [
        '--filter',
        '--group',
        '--exclude-group',
        '--test-suffix',
        '--covers',
        '--uses',
        '--cache-directory',
        '--cache-result-file',
        '--configuration',
        '--colors',
        '--test-directory',
        '--bootstrap',
        '--order-by',
        '--random-order-seed',
        '--log-junit',
        '--log-teamcity',
        '--log-events-text',
        '--log-events-verbose-text',
        '--coverage-clover',
        '--coverage-cobertura',
        '--coverage-crap4j',
        '--coverage-html',
        '--coverage-php',
        '--coverage-text',
        '--coverage-xml',
        '--assignee',
        '--issue',
        '--ticket',
        '--pr',
        '--pull-request',
        '--retry',
        '--shard',
        '--repeat',
    ];

    /**
     * @param  array<int, string>  $arguments
     */
    private function isFullRun(array $arguments): bool
    {
        if ($this->hasArgument('--filter', $arguments)) {
            return false;
        }

        $tokens = array_slice($arguments, 1);
        $skipNext = false;

        foreach ($tokens as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if ($arg === '') {
                continue;
            }

            if ($arg[0] === '-') {
                if (in_array($arg, self::FLAGS_WITH_VALUES, true)) {
                    $skipNext = true;
                }

                continue;
            }

            return false;
        }

        return true;
    }

    private static function runningOnCI(): bool
    {
        if (Environment::name() === Environment::CI) {
            return true;
        }

        return array_any(self::CI_ENVIRONMENT_VARIABLES, fn (string $environmentVariable): bool => getenv($environmentVariable) !== false);
    }
}
