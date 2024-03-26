<?php

test('visual snapshot of help command output', function () {
    $output = function () {
        $process = (new Symfony\Component\Process\Process(['php', 'bin/pest', '--help'], null, ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true']));

        $process->run();

        return removeAnsiEscapeSequences($process->getOutput());
    };

    expect($output())->toMatchSnapshot();
})->skipOnWindows();
