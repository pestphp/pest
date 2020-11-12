<?php

test('visual snapshot of help command output', function () {
    $snapshot = __DIR__ . '/../.snapshots/help-command.txt';

    $output = function () {
        $process = (new Symfony\Component\Process\Process(['php', 'bin/pest', '--help', '--colors=never']));

        $process->run();

        return $process->getOutput();
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        // Strip versions from start of snapshot
        $outputContent = preg_replace([
            '/Pest \s+\d+\.\d+\.\d+\s+/m',
            '/PHPUnit \d+\.\d+\.\d+\s+.*?\n/m'
        ], '', $output());

        file_put_contents($snapshot, $outputContent);
    }

    expect($output())->toContain(file_get_contents($snapshot));
})->skip(PHP_OS_FAMILY === 'Windows');
