<?php

use Symfony\Component\Process\Process;

test('parallel accepts an include path of whitespace as serial phpunit does', function (): void {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=2', '--configuration=tests/Fixtures/ParallelIncludePath.xml', '--include-path', ' '],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    $output = (string) removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput());

    expect($output)->not->toContain('Required argument for option "--include-path" is missing')
        ->and($process->getExitCode())->toBe(0, $output);
})->skipOnWindows();
