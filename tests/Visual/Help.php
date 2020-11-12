<?php

test('visual snapshot of help command output', function () {
    $output = function () {
        $process = (new Symfony\Component\Process\Process(['php', 'bin/pest', '--help', '--colors=never']));

        $process->run();

        return $process->getOutput();
    };

    expect($output())->toContain('Pest Options:');
});
