<?php

function normalize_windows_os_output(string $text): string
{
    $text = str_replace('\r', '', $text);

    return str_replace('\\', '/', $text);
}

test('visual snapshot of team city', function (string $testFile) {
    $testsPath = dirname(__DIR__)."/.tests/$testFile";

    $snapshot = implode(DIRECTORY_SEPARATOR, [
        dirname(__DIR__),
        '.snapshots',
        "$testFile.inc",
    ]);

    $output = function () use ($testsPath) {
        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', '--teamcity', $testsPath],
            dirname(__DIR__, levels: 2),
            [
                'EXCLUDE' => 'integration',
                'REBUILD_SNAPSHOTS' => false,
                'PARATEST' => 0,
                'COLLISION_IGNORE_DURATION' => 'true',
                'FLOW_ID' => '1234',
            ],
        ));

        $process->run();

        return $process->getOutput();
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        file_put_contents($snapshot, normalize_windows_os_output($output()));
    } elseif (! getenv('EXCLUDE')) {
        expect(normalize_windows_os_output($output()))->toEqual(file_get_contents($snapshot));
    }
})->with([
    'Failure.php',
    'SuccessOnly.php',
])->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'));
