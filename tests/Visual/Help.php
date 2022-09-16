<?php

use Pest\Console\Help;
use Symfony\Component\Console\Output\BufferedOutput;

test('visual snapshot of help command output', function () {
    $snapshot = __DIR__.'/../.snapshots/help-command.txt';

    if (getenv('REBUILD_SNAPSHOTS')) {
        $outputBuffer = new BufferedOutput();
        $plugin = new Help($outputBuffer);

        $plugin();

        file_put_contents($snapshot, $outputBuffer->fetch());
    }

    $output = function () {
        $process = (new Symfony\Component\Process\Process(['php', 'bin/pest', '--help'], null, ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true']));

        $process->run();

        return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
    };

    expect($output())->toContain(file_get_contents($snapshot));
})->skip(PHP_OS_FAMILY === 'Windows')->skip('Not supported yet.');
