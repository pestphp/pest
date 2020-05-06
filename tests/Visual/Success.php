<?php

test('visual snapshot of test suite on success', function () {
    $testsPath = dirname(__DIR__);
    $snapshot = implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        'success.txt',
    ]);

    $output = function () use ($testsPath) {
        $process = (new Symfony\Component\Process\Process(['./bin/pest'], dirname($testsPath), ['EXCLUDE' => 'integration', 'REBUILD_SNAPSHOTS' => false]));

        $process->run();

        return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        file_put_contents($snapshot, $output());
    } elseif (!getenv('EXCLUDE')) {
        $output = explode("\n", $output());
        array_pop($output);
        array_pop($output);
        assertStringContainsString(implode("\n", $output), file_get_contents($snapshot));
    }
})->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'));
