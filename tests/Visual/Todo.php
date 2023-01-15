<?php

use Symfony\Component\Process\Process;

$run = function (string $target, $decorated = false) {
    $process = new Process(['php', 'bin/pest', $target, '--colors=always'], dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    return $decorated ? $process->getOutput() : preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
};

$snapshot = function ($name) {
    $testsPath = dirname(__DIR__);

    return file_get_contents(implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        "$name.txt",
    ]));
};

test('todo', function () use ($run, $snapshot) {
    expect($run('--todo'))->toContain($snapshot('todo'));
})->skip(PHP_OS_FAMILY === 'Windows');
