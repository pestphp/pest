<?php

use Symfony\Component\Process\Process;

$run = function (string $target, $decorated = false) {
    $process = new Process(['php', 'bin/pest', $target], dirname(__DIR__, 2));

    $process->run();

    $output  = $decorated ? $process->getOutput() : preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());

    $output = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? str_replace("\n", "\r\n", $output) : $output;

    return $output;
};

$snapshot  = function ($name) {
    $testsPath = dirname(__DIR__);

    return file_get_contents(implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        "$name.txt",
    ]));
};

test('allows to run a single test', function () use ($run, $snapshot) {
    assertStringContainsString(
        $snapshot('allows-to-run-a-single-test'),
        $run('tests/Fixtures/DirectoryWithTests/ExampleTest.php'));
});

test('allows to run a directory', function () use ($run, $snapshot) {
    assertStringContainsString(
        $snapshot('allows-to-run-a-directory'),
        $run('tests/Fixtures')
    );
});

it('has ascii chars', function () use ($run, $snapshot) {
    assertStringContainsString(
        $snapshot('has-ascii-chars'),
        $run('tests/Fixtures/DirectoryWithTests/ExampleTest.php', true)
    );
});

it('disable decorating printer when colors is set to never', function () use ($snapshot) {
    $process = new Process([
        'php',
        './bin/pest',
        '--colors=never',
        'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
    ], dirname(__DIR__, 2));
    $process->run();
    $output = $process->getOutput();

    assertStringContainsString(
        $snapshot('disable-decorating-printer'),
        $output
    );
});
