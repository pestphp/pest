<?php

declare(strict_types=1);

namespace Pest\TestCaseFilters;

use Pest\Contracts\TestCaseFilter;
use Pest\Exceptions\MissingDependency;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\Panic;
use Pest\TestSuite;
use Symfony\Component\Process\Process;

final class GitDirtyTestCaseFilter implements TestCaseFilter
{
    /**
     * @var array<int, string>|null
     */
    private ?array $changedFiles = null;

    /**
     * Creates a new instance of the filter.
     */
    public function __construct(private readonly string $projectRoot)
    {
        // ...
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

    /**
     * Loads the changed files.
     */
    private function loadChangedFiles(): void
    {
        $process = new Process(['git', 'status', '--short', '--', '*.php']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new MissingDependency('Filter by dirty files', 'git');
        }

        $output = preg_split('/\R+/', $process->getOutput(), flags: PREG_SPLIT_NO_EMPTY);
        assert(is_array($output));

        $dirtyFiles = [];

        foreach ($output as $dirtyFile) {
            $dirtyFiles[substr($dirtyFile, 3)] = trim(substr($dirtyFile, 0, 3));
        }

        $dirtyFiles = array_filter($dirtyFiles, fn (string $status): bool => $status !== 'D');

        $dirtyFiles = array_map(
            fn (string $file, string $status): string => in_array($status, ['R', 'RM'], true)
                ? explode(' -> ', $file)[1]
                : $file, array_keys($dirtyFiles), $dirtyFiles,
        );

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
