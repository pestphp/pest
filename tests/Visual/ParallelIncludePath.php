<?php

use Symfony\Component\Process\Process;

$run = function (array $arguments): Process {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=2', ...$arguments, 'tests/Fixtures/ParallelIncludePath'],
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
    'value in the same argument' => [['--include-path=tests/Fixtures/ParallelIncludePath/lib']],
    'value as a separate argument' => [['--include-path', 'tests/Fixtures/ParallelIncludePath/lib']],
    'absolute path' => [['--include-path='.__DIR__.'/../Fixtures/ParallelIncludePath/lib']],
    'several paths' => [['--include-path=tests/Fixtures/Inheritance'.PATH_SEPARATOR.'tests/Fixtures/ParallelIncludePath/lib']],
    'directory name with an equals sign' => [['--include-path', 'tests/Fixtures/ParallelIncludePath/include=path']],
    'absolute directory name with an equals sign' => [['--include-path', __DIR__.'/../Fixtures/ParallelIncludePath/include=path']],
    'require in the bootstrap file' => [['--bootstrap=tests/Fixtures/ParallelIncludePath/bootstrap.inc', '--include-path=tests/Fixtures/ParallelIncludePath/lib']],
    'command line before the XML include path' => [['--configuration=tests/Fixtures/ParallelIncludePath.xml', '--include-path=tests/Fixtures/ParallelIncludePath/lib']],
    'command line before the XML include path in the bootstrap file' => [['--configuration=tests/Fixtures/ParallelIncludePath.xml', '--bootstrap=tests/Fixtures/ParallelIncludePath/bootstrap.inc', '--include-path=tests/Fixtures/ParallelIncludePath/lib']],
])->skipOnWindows();

test('parallel places the include path before the argument separator', function (): void {
    $process = new Process(
        ['php', '-r', 'require "vendor/autoload.php"; echo json_encode((new Pest\\Plugins\\Parallel\\Handlers\\IncludePath)->handleArguments(["--include-path", "lib", "--", "tests/Foo"]));'],
        dirname(__DIR__, 2),
    );

    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(json_decode($process->getOutput(), true))->toBe(['--include-path=lib', '--', 'tests/Foo']);
});

test('parallel fails to resolve requires without the include path', function () use ($run): void {
    $process = $run([]);
    $output = (string) removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput());

    expect($process->getExitCode())->not->toBe(0)
        ->and($output)->toContain("Failed opening required 'parallel-include-path.inc'");
})->skipOnWindows();
