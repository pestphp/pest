<?php

use Symfony\Component\Process\Process;

$run = function (array $arguments): Process {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=2', ...$arguments, 'tests/Fixtures/Suites/ParallelIncludePath'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    return $process;
};

test('parallel resolves requires through the include path', function (array $arguments) use ($run): void {
    $process = $run($arguments);
    $output = (string) removeAnsiEscapeSequences($process->getOutput());

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$output)
        ->and($output)->toContain('Tests:    2 passed');
})->with([
    'relative path with equals sign' => [['--include-path=tests/Fixtures/Suites/ParallelIncludePath/lib']],
    'relative path as separate value' => [['--include-path', 'tests/Fixtures/Suites/ParallelIncludePath/lib']],
    'absolute path' => [['--include-path='.dirname(__DIR__).'/Fixtures/Suites/ParallelIncludePath/lib']],
    'path with an equals sign' => [['--include-path', 'tests/Fixtures/Suites/ParallelIncludePath/include=path']],
    'several paths' => [['--include-path=tests/Fixtures/Inheritance'.PATH_SEPARATOR.'tests/Fixtures/Suites/ParallelIncludePath/lib']],
])->skipOnWindows();

test('parallel fails to resolve requires without the include path', function () use ($run): void {
    $process = $run([]);
    $output = (string) removeAnsiEscapeSequences($process->getOutput());

    expect($process->getExitCode())->toBe(1, $process->getErrorOutput().$output)
        ->and($output)->toContain('Tests:    2 failed');
})->skipOnWindows();
