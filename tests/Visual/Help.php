<?php

test('visual snapshot of help command output', function () {
    $snapshot = __DIR__.'/../.snapshots/help-command.txt';

    $output = function () {
        $process = (new Symfony\Component\Process\Process(['php', 'bin/pest', '--help'], null, ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true']));

        $process->run();

        return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        file_put_contents($snapshot, $output());

        $this->markTestSkipped('Snapshot rebuilt.');
    }

    expect($output())->toContain(file_get_contents($snapshot));
})->skipOnWindows();
