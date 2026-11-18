<?php

declare(strict_types=1);

namespace Pest\TestCaseFilters;

use Pest\Contracts\TestCaseFilter;
use Pest\Exceptions\MissingDependency;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\Panic;
use Pest\Support\Git;
use Pest\TestSuite;

final class GitDirtyTestCaseFilter implements TestCaseFilter
{
    /**
     * @var array<int, string>|null
     */
    private ?array $changedFiles = null;

    public function __construct(private readonly string $projectRoot)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function accept(string $testCaseFilename): bool
    {
        if ($this->changedFiles === null) {
            $this->loadChangedFiles();
        }

        assert(is_array($this->changedFiles));

        $relativePath = str_replace($this->projectRoot, '', $testCaseFilename);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        if (str_starts_with($relativePath, '/')) {
            $relativePath = substr($relativePath, 1);
        }

        return in_array($relativePath, $this->changedFiles, true);
    }

    private function loadChangedFiles(): void
    {
        $git = new Git($this->projectRoot, timeout: 60.0);
        $status = $git->raw(['status', '--porcelain=v1', '-z', '--no-renames', '--', '*.php']);
        $prefix = $git->raw(['rev-parse', '--show-prefix']);

        if ($status === null || $prefix === null) {
            throw new MissingDependency('Filter by dirty files', 'git');
        }

        $pathOffset = 3 + strlen(rtrim($prefix, "\r\n"));
        $dirtyFiles = [];

        foreach (explode("\0", $status) as $entry) {
            if ($entry === '' || trim(substr($entry, 0, 2)) === 'D') {
                continue;
            }

            $dirtyFiles[] = substr($entry, $pathOffset);
        }

        $dirtyFiles = array_filter(
            $dirtyFiles,
            fn (string $file): bool => str_starts_with('.'.DIRECTORY_SEPARATOR.$file, TestSuite::getInstance()->testPath)
                || str_starts_with($file, TestSuite::getInstance()->testPath)
        );

        $dirtyFiles = array_values($dirtyFiles);

        if ($dirtyFiles === []) {
            Panic::with(new NoDirtyTestsFound);
        }

        $this->changedFiles = $dirtyFiles;
    }
}
