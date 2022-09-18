<?php

test('visual snapshot of test suite on success', function () {
    $testsPath = dirname(__DIR__);
    $snapshot = implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        'success.txt',
    ]);

    $output = function () use ($testsPath) {
        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest'],
            dirname($testsPath),
            ['EXCLUDE' => 'integration', 'REBUILD_SNAPSHOTS' => false, 'PARATEST' => 0, 'COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
        ));

        $process->run();

        return preg_replace([
            '#\\x1b[[][^A-Za-z]*[A-Za-z]#',
            '/(Tests\\\PHPUnit\\\CustomAffixes\\\InvalidTestName)([A-Za-z0-9]*)/',
        ], [
            '',
            '$1',
        ], $process->getOutput());
    };

    $outputContent = explode("\n", $output());
    $outputContent = array_map(fn (string $line) => trim($line), $outputContent);
    $outputContent = array_filter($outputContent, fn (string $line) => $line !== '');

    array_pop($outputContent);
    array_pop($outputContent);

    if (getenv('REBUILD_SNAPSHOTS')) {
        file_put_contents($snapshot, implode("\n", $outputContent));
    } elseif (! getenv('EXCLUDE')) {
        expect(implode("\n", $outputContent))->toBe(file_get_contents($snapshot));
    }
})->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'))
    ->skip(PHP_OS_FAMILY === 'Windows');
