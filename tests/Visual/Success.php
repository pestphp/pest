<?php

use Symfony\Component\Process\Process;

test('visual snapshot of test suite on success', function (): void {
    $testsPath = dirname(__DIR__);
    $snapshot = implode(DIRECTORY_SEPARATOR, [
        $testsPath,
        '.snapshots',
        'success.txt',
    ]);

    $output = function () use ($testsPath): ?string {
        $process = (new Process(
            ['php', '-d', 'memory_limit=-1', 'bin/pest'],
            dirname($testsPath),
            ['EXCLUDE' => 'integration', '--exclude-group' => 'integration', 'REBUILD_SNAPSHOTS' => false, 'PARATEST' => 0, 'COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
        ));

        $process->setTimeout(300.0);

        $process->run();

        return preg_replace([
            '#\\x1b[[][^A-Za-z]*[A-Za-z]#',
            '#\\x1b\\]8;[^\\x1b\\x07]*(?:\\x1b\\\\|\\x07)#',
            '/(Tests\\\PHPUnit\\\CustomAffixes\\\InvalidTestName)([A-Za-z0-9]*)/',
        ], [
            '',
            '',
            '$1',
        ], $process->getOutput());
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        $outputContent = explode("\n", (string) $output());
        array_pop($outputContent);
        array_pop($outputContent);
        array_pop($outputContent);

        file_put_contents($snapshot, implode("\n", $outputContent));
    } elseif (! getenv('EXCLUDE')) {
        $output = explode("\n", (string) $output());
        array_pop($output);
        array_pop($output);

        expect(implode("\n", $output))->toContain(file_get_contents($snapshot));
    }
})->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'))
    ->skipOnWindows();
