<?php

test('json mode outputs JSON for passing tests', function () {
    $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['PEST_JSON_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
    unset($env['COLLISION_PRINTER']);

    $process = (new Symfony\Component\Process\Process(
        ['php', 'bin/pest', 'tests/Features/Json.php', '--json', '--filter=has plugin'],
        null,
        $env
    ));

    $process->run();

    $decoded = json_decode(trim($process->getOutput()), true);

    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('pass');
})->skipOnWindows();

test('json mode outputs JSON with failures when tests fail', function () {
    $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['PEST_JSON_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
    unset($env['COLLISION_PRINTER']);

    $process = (new Symfony\Component\Process\Process(
        ['php', 'bin/pest', 'tests/Fixtures/JsonFailingTest.php', '--json'],
        null,
        $env
    ));

    $process->run();

    $decoded = json_decode(trim($process->getOutput()), true);

    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('fail')
        ->and($decoded['failures'])->toBeArray()
        ->and($decoded['failures'][0])->toHaveKeys(['test', 'message', 'location', 'trace']);
})->skipOnWindows();
