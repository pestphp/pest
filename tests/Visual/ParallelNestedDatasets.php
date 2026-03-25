<?php

use Symfony\Component\Process\Process;

$fixture = function (): array {
    $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.'Features'.DIRECTORY_SEPARATOR.'ParallelNestedDatasetRepro';
    $datasetsDirectory = $directory.DIRECTORY_SEPARATOR.'Datasets'.DIRECTORY_SEPARATOR.'Nested';
    $target = $directory.DIRECTORY_SEPARATOR.'TestFileWithNestedDataset.php';

    if (! is_dir($datasetsDirectory)) {
        mkdir($datasetsDirectory, 0777, true);
    }

    file_put_contents($datasetsDirectory.DIRECTORY_SEPARATOR.'Users.php', <<<'PHP'
<?php

dataset('nested.users', [
    ['alice'],
    ['bob'],
]);
PHP);

    file_put_contents($target, <<<'PHP'
<?php

test('loads nested dataset', function (string $name) {
    expect($name)->not->toBeEmpty();
})->with('nested.users');
PHP);

    return [$directory, 'tests/Features/ParallelNestedDatasetRepro/TestFileWithNestedDataset.php'];
};

$cleanup = function (string $directory): void {
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
};

$run = function (string $target, bool $parallel = false): array {
    $command = ['php', 'bin/pest', $target, '--colors=never'];

    if ($parallel) {
        $command[] = '--parallel';
        $command[] = '--processes=2';
    }

    $process = new Process($command, dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    return [
        'exitCode' => $process->getExitCode(),
        'output' => removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput()),
    ];
};

test('parallel reports missing nested datasets without a passing summary', function () use ($cleanup, $fixture, $run) {
    [$directory, $target] = $fixture();

    try {
        $serial = $run($target);
        $parallel = $run($target, true);

        expect($serial['exitCode'])->toBe(2)
            ->and($parallel['exitCode'])->toBe(2)
            ->and($serial['output'])->toContain('INFO  No tests found.')
            ->and($parallel['output'])->toContain('INFO  No tests found.')
            ->and($parallel['output'])->toContain('Parallel: 2 processes')
            ->and($parallel['output'])->not->toContain('passed');
    } finally {
        $cleanup($directory);
    }
})->skipOnWindows();
