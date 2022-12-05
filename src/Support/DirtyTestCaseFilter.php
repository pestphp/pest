<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Contracts\TestCaseFilter;
use Symfony\Component\Process\Process;

final class DirtyTestCaseFilter implements TestCaseFilter
{
    /**
     * @var array<string>
     */
    private array $changedFiles = [];

    public function __construct(private string $projectRoot)
    {
        $this->loadDiff();
    }

    public function canLoad(string $suiteClassFile): bool
    {
        $relativePath = str_replace($this->projectRoot, '', $suiteClassFile);

        if (str_starts_with($relativePath, '/')) {
            $relativePath = substr($relativePath, 1);
        }

        return in_array($relativePath, $this->changedFiles, true);
    }

    private function loadDiff(): void
    {
        $process = new Process([
            'git',
            'diff',
            '--name-only',
            'HEAD',
            '--',
            '*.php',
        ]);
        $process->run();

        $this->changedFiles = explode(PHP_EOL, trim($process->getOutput()));
    }
}
