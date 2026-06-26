<?php

use Symfony\Component\Process\Process;

test('global afterAll hook runs after the suite', function () {
    $marker = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-afterall-'.uniqid();

    $process = new Process(
        ['php', 'bin/pest', 'tests/.tests/GlobalAfterAll'],
        dirname(__DIR__, 2),
        ['PEST_AFTERALL_MARKER' => $marker],
    );

    $process->run();

    expect(file_exists($marker))->toBeTrue();

    if (file_exists($marker)) {
        unlink($marker);
    }
})->skipOnWindows();
