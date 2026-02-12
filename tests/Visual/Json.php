<?php

test('json mode outputs JSON for passing tests', function () {
    $output = function () {
        $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['PEST_JSON_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
        $env['COLLISION_PRINTER'] = 'DefaultPrinter';

        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', 'tests/Features/Json.php', '--json', '--filter=has plugin'],
            null,
            $env
        ));

        $process->run();

        return trim($process->getOutput());
    };

    $result = $output();

    $decoded = json_decode($result, true);
    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('pass');
})->skipOnWindows();

test('json mode outputs JSON with failures when tests fail', function () {
    $testsPath = dirname(__DIR__);
    $fixturesPath = implode(DIRECTORY_SEPARATOR, [$testsPath, 'Fixtures', '.temp']);

    if (! is_dir($fixturesPath)) {
        mkdir($fixturesPath, 0777, true);
    }

    $testFile = $fixturesPath.'/JsonFailingTest.php';
    file_put_contents($testFile, <<<'PHP'
<?php
test('failing test', function () {
    expect(true)->toBeFalse();
});
PHP);

    $output = function () use ($testFile) {
        $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['PEST_JSON_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
        $env['COLLISION_PRINTER'] = 'DefaultPrinter';

        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', $testFile, '--json'],
            null,
            $env
        ));

        $process->run();

        return trim($process->getOutput());
    };

    $result = $output();

    unlink($testFile);

    $decoded = json_decode($result, true);
    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('fail')
        ->and($decoded['failures'])->toBeArray()
        ->and($decoded['failures'][0])->toHaveKeys(['test', 'message', 'location', 'trace']);
})->skipOnWindows();
