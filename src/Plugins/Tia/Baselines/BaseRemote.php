<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Baselines;

use Pest\Plugins\Tia\WatchPatterns;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
abstract readonly class BaseRemote // @pest-arch-ignore-line
{
    public function __construct(protected WatchPatterns $watchPatterns) {}

    abstract public function detect(string $projectRoot): ?string;

    abstract public function cliName(): string;

    abstract public function installUrl(): string;

    abstract public function loginCommand(): string;

    abstract public function providerLabel(): string;

    /**
     * @return array{0: ?string, 1: ?array{kind: string, message: string}}
     */
    abstract public function latestSuccessfulRunId(string $repo): array;

    abstract public function artifactSize(string $repo, string $runId): ?int;

    /**
     * @return array<int, string>
     */
    abstract public function downloadCommand(string $repo, string $runId, string $destDir): array;

    public function cliExists(): bool
    {
        $process = new Process(['which', $this->cliName()]);
        $process->run();

        return $process->isSuccessful();
    }

    public function cliAuthenticated(): bool
    {
        $process = new Process([$this->cliName(), 'auth', 'status']);
        $process->setTimeout(10.0);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @return array{kind: string, message: string}
     */
    public function classifyError(string $output): array
    {
        $output = trim($output);

        if ($output === '') {
            return ['kind' => 'unknown', 'message' => 'unknown error'];
        }

        foreach ($this->diagnoses() as $kind => $diagnosis) {
            if (preg_match($diagnosis['pattern'], $output) === 1) {
                return ['kind' => $kind, 'message' => $diagnosis['message']];
            }
        }

        return ['kind' => 'unknown', 'message' => trim(strtok($output, "\n"))];
    }

    protected function readOriginUrl(string $projectRoot): ?string // @pest-arch-ignore-line
    {
        $gitConfig = $projectRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config';

        if (! is_file($gitConfig)) {
            return null;
        }

        $content = @file_get_contents($gitConfig);

        if ($content === false) {
            return null;
        }

        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $content, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function parseRemoteUrl(string $url): ?array // @pest-arch-ignore-line
    {
        if (preg_match('#^git@([^:]+):([\w./-]+?)(?:\.git)?$#', $url, $m) === 1) {
            return [$m[1], $m[2]];
        }

        if (preg_match('#^https?://([^/]+)/([\w./-]+?)(?:\.git)?/?$#', $url, $m) === 1) {
            return [$m[1], $m[2]];
        }

        if (preg_match('#^ssh://(?:[^@/]+@)?([^:/]+)(?::\d+)?/([\w./-]+?)(?:\.git)?/?$#i', $url, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return null;
    }

    protected function resolveDefaultBranch(string $repo): string // @pest-arch-ignore-line
    {
        $configured = $this->watchPatterns->defaultBranch();

        if ($configured !== null) {
            return $configured;
        }

        $process = new Process(['git', 'symbolic-ref', 'refs/remotes/origin/HEAD']);
        $process->setTimeout(5.0);
        $process->run();

        if ($process->isSuccessful()) {
            $ref = trim($process->getOutput());

            $branch = str_replace('refs/remotes/origin/', '', $ref);

            if ($branch !== '') {
                return $branch;
            }
        }

        return $this->remoteDefaultBranch($repo) ?? 'main';
    }

    abstract protected function remoteDefaultBranch(string $repo): ?string; // @pest-arch-ignore-line

    /**
     * @return array<string, array{pattern: string, message: string}>
     */
    abstract protected function diagnoses(): array; // @pest-arch-ignore-line
}
