<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Baselines;

use Symfony\Component\Process\Process;

/**
 * @internal
 */
final readonly class GitHubRemote extends BaseRemote
{
    private const string DEFAULT_WORKFLOW_FILE = 'tia-baseline.yml';

    private const string ARTIFACT_NAME = 'pest-tia-baseline';

    public function detect(string $projectRoot): ?string
    {
        $url = $this->readOriginUrl($projectRoot);

        if ($url === null) {
            return null;
        }

        $parsed = $this->parseRemoteUrl($url);

        if ($parsed === null) {
            return null;
        }

        [$hostname, $path] = $parsed;

        return strcasecmp($hostname, 'github.com') === 0 ? $path : null;
    }

    public function cliName(): string
    {
        return 'gh';
    }

    public function installUrl(): string
    {
        return 'https://cli.github.com';
    }

    public function loginCommand(): string
    {
        return 'gh auth login';
    }

    public function providerLabel(): string
    {
        return 'GitHub';
    }

    public function latestSuccessfulRunId(string $repo): array
    {
        $process = new Process([
            'gh', 'run', 'list',
            '-R', $repo,
            '--workflow', $this->workflowFile(),
            '--status', 'success',
            '--limit', '1',
            '--json', 'databaseId',
            '--jq', '.[0].databaseId // empty',
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return [null, $this->classifyError($process->getErrorOutput().$process->getOutput())];
        }

        $runId = trim($process->getOutput());

        return [$runId === '' ? null : $runId, null];
    }

    public function artifactSize(string $repo, string $runId): ?int
    {
        $process = new Process([
            'gh', 'api',
            sprintf('repos/%s/actions/runs/%s/artifacts', $repo, $runId),
            '--jq', sprintf(
                '.artifacts[] | select(.name === "%s") | .size_in_bytes',
                self::ARTIFACT_NAME,
            ),
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $size = trim($process->getOutput());

        return is_numeric($size) ? (int) $size : null;
    }

    public function downloadCommand(string $repo, string $runId, string $destDir): array
    {
        return [
            'gh', 'run', 'download', $runId,
            '-R', $repo,
            '-n', self::ARTIFACT_NAME,
            '-D', $destDir,
        ];
    }

    protected function remoteDefaultBranch(string $repo): ?string // @pest-arch-ignore-line
    {
        $process = new Process([
            'gh', 'api',
            sprintf('repos/%s', $repo),
            '--jq', '.default_branch',
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        return $branch !== '' ? $branch : null;
    }

    protected function diagnoses(): array // @pest-arch-ignore-line
    {
        return [
            'network' => [
                'pattern' => '/could not resolve host|connection refused|connection reset|temporary failure in name resolution|network is unreachable|no route to host|i\/o timeout|tls handshake|getaddrinfo/i',
                'message' => 'network error (offline or DNS unreachable). Try again when connected.',
            ],
            'gh-auth' => [
                'pattern' => '/authentication failed|not logged in|requires authentication|bad credentials|401/i',
                'message' => 'authentication failed — run `gh auth login` and retry.',
            ],
            'rate-limit' => [
                'pattern' => '/rate limit|too many requests|secondary rate limit/i',
                'message' => 'GitHub API rate limit hit — try again later.',
            ],
            'not-found' => [
                'pattern' => '/404|not found|repository not found/i',
                'message' => 'workflow or artifact not found in repo.',
            ],
            'forbidden' => [
                'pattern' => '/403|forbidden|access denied/i',
                'message' => 'access denied — check that your `gh` token has repo + actions read scope.',
            ],
        ];
    }

    private function workflowFile(): string
    {
        return $this->watchPatterns->baselineWorkflow() ?? self::DEFAULT_WORKFLOW_FILE;
    }
}
