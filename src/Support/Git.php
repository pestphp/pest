<?php

declare(strict_types=1);

namespace Pest\Support;

use Symfony\Component\Process\Process;

/**
 * @internal
 */
final readonly class Git
{
    private const float TIMEOUT = 5.0;

    public function __construct(
        private ?string $directory = null,
        private float $timeout = self::TIMEOUT,
    ) {}

    public function withTimeout(float $timeout): self
    {
        return new self($this->directory, $timeout);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public function raw(array $arguments): ?string
    {
        $result = $this->result($arguments);

        return $result['exitCode'] === 0 ? $result['output'] : null;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public function output(array $arguments): ?string
    {
        $output = $this->raw($arguments);

        if ($output === null) {
            return null;
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public function succeeds(array $arguments): bool
    {
        return $this->result($arguments)['exitCode'] === 0;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{exitCode: int, output: string}
     */
    public function result(array $arguments, ?string $input = null): array
    {
        $process = new Process(['git', ...$arguments], $this->directory);
        $process->setTimeout($this->timeout);

        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run();

        return [
            'exitCode' => $process->getExitCode() ?? 1,
            'output' => $process->getOutput(),
        ];
    }

    public function isRepository(): bool
    {
        return $this->succeeds(['rev-parse', '--git-dir']);
    }

    public function hasCommits(): bool
    {
        return $this->succeeds(['rev-parse', '--verify', '--quiet', 'HEAD']);
    }

    public function hasRemote(): bool
    {
        return $this->output(['remote']) !== null;
    }

    public function hasRef(string $ref): bool
    {
        return $this->output(['rev-parse', '--verify', '--quiet', $ref]) !== null;
    }

    public function show(string $sha, string $path): ?string
    {
        return $this->raw(['show', $sha.':'.$path]);
    }

    public function subdirectoryPrefix(): ?string
    {
        $prefix = $this->output(['rev-parse', '--show-prefix']);

        if ($prefix === null) {
            return null;
        }

        return rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $prefix), '/');
    }
}
