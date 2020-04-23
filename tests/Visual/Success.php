<?php

test('visual snapshot of test suite on success', function () {
    $rootPath = dirname(__DIR__, 2);
    $snapshot = implode(DIRECTORY_SEPARATOR, [$rootPath, '.temp', 'success.txt']);

    $output = function () use ($rootPath) {
        $process = (new Symfony\Component\Process\Process(['./bin/pest'], $rootPath, ['EXCLUDE' => 'integration', 'REBUILD_SNAPSHOTS' => false]));

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
    } else {
        assertTrue(true);
    }
})->group('integration');
