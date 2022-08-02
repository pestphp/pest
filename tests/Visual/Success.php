<?php

test('visual snapshot of test suite on success', function () {
    $testsPath = dirname(__DIR__);
    $snapshot  = implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        'success.txt',
    ]);

    $output      = function () use ($testsPath) {
        $process = (new Symfony\Component\Process\Process(['php', 'bin/pest'], dirname($testsPath), ['EXCLUDE' => 'integration', 'REBUILD_SNAPSHOTS' => false, 'PARATEST' => 0]));

        $process->run();

        return preg_replace([
            '#\\x1b[[][^A-Za-z]*[A-Za-z]#',
            '/(Tests\\\PHPUnit\\\CustomAffixes\\\InvalidTestName)([A-Za-z0-9]*)/',
        ], [
            '',
            '$1',
        ], $process->getOutput());
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        // Strip time from end of snapshot
        $outputContent = preg_replace('/Time\: \s+\d+\.\d+s\s+/m', '', $output());

        file_put_contents($snapshot, $outputContent);
    } elseif (!getenv('EXCLUDE')) {
        $output = explode("\n", $output());
        array_pop($output);
        array_pop($output);

        expect(implode("\n", $output))->toContain(file_get_contents($snapshot));
    }
})->skip(!getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'))
    ->skip(PHP_OS_FAMILY === 'Windows');
