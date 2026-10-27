<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Baselines;

use Symfony\Component\Process\Process;

/**
 * @internal
 */
final readonly class GitLabRemote extends BaseRemote
{
    private const string DEFAULT_JOB_NAME = 'tia-baseline';

    private const int PIPELINES_PER_PAGE = 20;

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

        if (strcasecmp($hostname, 'github.com') === 0) {
            return null;
        }

        if (strcasecmp($hostname, 'gitlab.com') === 0) {
            return $path;
        }

        if (! $this->cliExists()) {
            return null;
        }

        return $path;
    }

    public function cliName(): string
    {
        return 'glab';
    }

    public function installUrl(): string
    {
        return 'https://gitlab.com/gitlab-org/cli#installation';
    }

    public function loginCommand(): string
    {
        return 'glab auth login';
    }

    public function providerLabel(): string
    {
        return 'GitLab';
    }

    public function latestSuccessfulRunId(string $repo): array
    {
        $encodedRepo = rawurlencode($repo);
        $jobName = $this->jobName();
        $defaultBranch = $this->resolveDefaultBranch($repo);

        $process = new Process([
            'glab', 'api',
            sprintf(
                'projects/%s/pipelines?ref=%s&status=success&order_by=id&sort=desc&per_page=%d',
                $encodedRepo,
                rawurlencode($defaultBranch),
                self::PIPELINES_PER_PAGE,
            ),
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return [null, $this->classifyError($process->getErrorOutput().$process->getOutput())];
        }

        foreach ($this->pipelineIds($process->getOutput()) as $pipelineId) {
            if ($this->findJob($encodedRepo, $pipelineId, $jobName) !== null) {
                return [$pipelineId, null];
            }
        }

        return [null, null];
    }

    public function artifactSize(string $repo, string $runId): ?int
    {
        $job = $this->findJob(rawurlencode($repo), $runId, $this->jobName());

        if ($job === null || ! is_array($job['artifacts'] ?? null)) {
            return null;
        }

        $size = 0;

        foreach ($job['artifacts'] as $artifact) {
            if (is_array($artifact) && is_int($artifact['size'] ?? null)) {
                $size += $artifact['size'];
            }
        }

        return $size;
    }

    public function downloadCommand(string $repo, string $runId, string $destDir): array
    {
        return [
            'glab', 'job', 'artifact',
            $this->resolveDefaultBranch($repo),
            $this->jobName(),
            '-R', $repo,
            '--path', $destDir,
        ];
    }

    protected function remoteDefaultBranch(string $repo): ?string // @pest-arch-ignore-line
    {
        $process = new Process([
            'glab', 'api',
            sprintf('projects/%s', rawurlencode($repo)),
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $project = json_decode($process->getOutput(), true);
        $branch = is_array($project) ? ($project['default_branch'] ?? null) : null;

        return is_string($branch) && $branch !== '' ? $branch : null;
    }

    protected function diagnoses(): array // @pest-arch-ignore-line
    {
        return [
            'network' => [
                'pattern' => '/could not resolve host|connection refused|connection reset|temporary failure|network is unreachable|no route to host|tls handshake|dial tcp/i',
                'message' => 'network error (offline or DNS unreachable). Try again when connected.',
            ],
            'glab-auth' => [
                'pattern' => '/authentication failed|not logged in|requires authentication|unauthorized|401|token expired|invalid token/i',
                'message' => 'authentication failed — run `glab auth login` and retry.',
            ],
            'rate-limit' => [
                'pattern' => '/rate limit|too many requests|429/i',
                'message' => 'GitLab API rate limit hit — try again later.',
            ],
            'not-found' => [
                'pattern' => '/404|not found|project not found|does not exist/i',
                'message' => 'pipeline, job, or artifact not found in project.',
            ],
            'forbidden' => [
                'pattern' => '/403|forbidden|access denied|insufficient scope/i',
                'message' => 'access denied — check that your `glab` token has read_api scope.',
            ],
        ];
    }

    private function jobName(): string
    {
        return $this->watchPatterns->baselineJob() ?? self::DEFAULT_JOB_NAME;
    }

    /**
     * @return array<int, string>
     */
    private function pipelineIds(string $output): array
    {
        $pipelines = json_decode($output, true);

        if (! is_array($pipelines)) {
            return [];
        }

        $ids = [];

        foreach ($pipelines as $pipeline) {
            $id = is_array($pipeline) ? ($pipeline['id'] ?? null) : null;

            if (is_int($id) || (is_string($id) && $id !== '')) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findJob(string $encodedRepo, string $pipelineId, string $jobName): ?array
    {
        $process = new Process([
            'glab', 'api',
            sprintf('projects/%s/pipelines/%s/jobs', $encodedRepo, $pipelineId),
        ]);
        $process->setTimeout(30.0);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $jobs = json_decode($process->getOutput(), true);

        if (! is_array($jobs)) {
            return null;
        }

        foreach ($jobs as $job) {
            if (! is_array($job) || ($job['name'] ?? null) !== $jobName) {
                continue;
            }

            if (($job['status'] ?? null) !== 'success') {
                continue;
            }

            /** @var array<string, mixed> $job */
            return $job;
        }

        return null;
    }
}
