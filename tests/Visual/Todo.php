<?php

use Symfony\Component\Process\Process;

$run = function (string $target, bool $parallel) {
    $process = new Process(['php', 'bin/pest', $target, $parallel ? '--parallel' : '', '--colors=always'], dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    expect($process->getExitCode())->toBe(0);

    return removeAnsiEscapeSequences($process->getOutput());
};

$snapshot = function ($name) {
    $testsPath = dirname(__DIR__);

    return file_get_contents(implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        "$name.txt",
    ]));
};

test('todos', function () use ($run, $snapshot) {
    expect($run('--todos', false))->toContain($snapshot('todos'));
})->skipOnWindows();

test('todos in parallel', function () use ($run, $snapshot) {
    expect($run('--todos', true))->toContain($snapshot('todos'));
})->skipOnWindows();

test('todo', function () use ($run, $snapshot) {
    expect($run('--todo', false))->toContain($snapshot('todo'));
})->skipOnWindows();

test('todo in parallel', function () use ($run, $snapshot) {
    expect($run('--todo', true))->toContain($snapshot('todo'));
})->skipOnWindows();
