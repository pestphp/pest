<?php

use Symfony\Component\Process\Process;

$run = function (string $target) {
    $process = new Process(['php', 'bin/pest', $target, '--coverage'], dirname(__DIR__, 2));

    $process->run();

    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
};

$snapshot  = function ($name) {
    $testsPath = dirname(__DIR__);

    return file_get_contents(implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        "$name.txt",
    ]));
};

test('coverage', function () use ($run, $snapshot) {
    $text = $run('tests/Playground.php');

    assertStringContainsString(
        $snapshot('coverage'),
        $run('tests/Playground.php')
    );
})->skip(PHP_OS_FAMILY === 'Windows');
