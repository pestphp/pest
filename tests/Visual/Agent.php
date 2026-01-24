<?php

test('agent mode outputs JSON for passing tests', function () {
    $output = function () {
        $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
        $env['COLLISION_PRINTER'] = 'DefaultPrinter';

        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', 'tests/Features/Agent.php', '--agent', '--filter=has plugin'],
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

test('agent mode outputs JSON with failures when tests fail', function () {
    $testsPath = dirname(__DIR__);
    $fixturesPath = implode(DIRECTORY_SEPARATOR, [$testsPath, 'Fixtures', '.temp']);

    if (! is_dir($fixturesPath)) {
        mkdir($fixturesPath, 0777, true);
    }

    $testFile = $fixturesPath.'/AgentFailingTest.php';
    file_put_contents($testFile, <<<'PHP'
<?php
test('failing test', function () {
    expect(true)->toBeFalse();
});
PHP);

    $output = function () use ($testFile) {
        $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
        $env['COLLISION_PRINTER'] = 'DefaultPrinter';

        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', $testFile, '--agent'],
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

test('agent mode auto-activates with CLAUDECODE env var', function () {
    $output = function () {
        $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
        $env['CLAUDECODE'] = '1';
        $env['COLLISION_PRINTER'] = 'DefaultPrinter';

        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', 'tests/Features/Agent.php', '--filter=has plugin'],
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

test('agent mode auto-activates with OPENCODE env var', function () {
    $output = function () {
        $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
        $env['OPENCODE'] = '1';
        $env['COLLISION_PRINTER'] = 'DefaultPrinter';

        $process = (new Symfony\Component\Process\Process(
            ['php', 'bin/pest', 'tests/Features/Agent.php', '--filter=has plugin'],
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

test('agent mode enforces minimum coverage threshold', function () {
    $testsPath = dirname(__DIR__);
    $fixturesPath = implode(DIRECTORY_SEPARATOR, [$testsPath, 'Fixtures', '.temp']);

    if (! is_dir($fixturesPath)) {
        mkdir($fixturesPath, 0777, true);
    }

    $testFile = $fixturesPath.'/AgentCoverageTest.php';
    file_put_contents($testFile, <<<'PHP'
<?php
function coveredFunction() {
    return true;
}

function uncoveredFunction() {
    return false;
}

test('coverage test', function () {
    expect(coveredFunction())->toBeTrue();
});
PHP);

    $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
    $env['COLLISION_PRINTER'] = 'DefaultPrinter';

    $process = (new Symfony\Component\Process\Process(
        ['php', 'bin/pest', $testFile, '--agent', '--coverage', '--min=100'],
        null,
        $env
    ));

    $process->run();
    $exitCode = $process->getExitCode();

    unlink($testFile);

    expect($exitCode)->toBe(1);
})->skip(! \Pest\Support\Coverage::isAvailable(), 'Coverage is not available')->skipOnWindows();

test('agent mode passes when coverage meets minimum', function () {
    $testsPath = dirname(__DIR__);
    $fixturesPath = implode(DIRECTORY_SEPARATOR, [$testsPath, 'Fixtures', '.temp']);

    if (! is_dir($fixturesPath)) {
        mkdir($fixturesPath, 0777, true);
    }

    $testFile = $fixturesPath.'/AgentCoveragePassTest.php';
    file_put_contents($testFile, <<<'PHP'
<?php
function allCoveredFunction() {
    return true;
}

test('coverage test', function () {
    expect(allCoveredFunction())->toBeTrue();
});
PHP);

    $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
    $env['COLLISION_PRINTER'] = 'DefaultPrinter';

    $process = (new Symfony\Component\Process\Process(
        ['php', 'bin/pest', $testFile, '--agent', '--coverage', '--min=0'],
        null,
        $env
    ));

    $process->run();
    $exitCode = $process->getExitCode();
    $output = trim($process->getOutput());

    unlink($testFile);

    $decoded = json_decode($output, true);
    expect($exitCode)->toBe(0)
        ->and($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('pass')
        ->and($decoded)->toHaveKey('coverage');
})->skip(! \Pest\Support\Coverage::isAvailable(), 'Coverage is not available')->skipOnWindows();

test('agent mode includes memory metadata when --memory is used', function () {
    $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
    $env['COLLISION_PRINTER'] = 'DefaultPrinter';

    $process = (new Symfony\Component\Process\Process(
        ['php', 'bin/pest', 'tests/Features/Agent.php', '--agent', '--memory', '--filter=has plugin'],
        null,
        $env
    ));

    $process->run();
    $output = trim($process->getOutput());

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('pass')
        ->and($decoded)->toHaveKey('memory')
        ->and($decoded['memory'])->toBeFloat()
        ->and($decoded['memory'])->toBeGreaterThan(0);
})->skipOnWindows();

test('agent mode includes shard metadata when --shard is used', function () {
    $env = array_filter(getenv(), fn ($key) => ! in_array($key, ['CLAUDECODE', 'OPENCODE', 'PEST_AGENT_OUTPUT'], true), ARRAY_FILTER_USE_KEY);
    $env['COLLISION_PRINTER'] = 'DefaultPrinter';

    $process = (new Symfony\Component\Process\Process(
        ['php', 'bin/pest', 'tests/Features/Agent.php', '--agent', '--shard=1/2'],
        null,
        $env
    ));

    $process->run();
    $output = trim($process->getOutput());

    $decoded = json_decode($output, true);
    expect($decoded)->toBeArray()
        ->and($decoded['status'])->toBe('pass')
        ->and($decoded)->toHaveKey('shard')
        ->and($decoded['shard'])->toBeArray()
        ->and($decoded['shard'])->toHaveKeys(['index', 'total', 'testsRan', 'testsCount'])
        ->and($decoded['shard']['index'])->toBe(1)
        ->and($decoded['shard']['total'])->toBe(2)
        ->and($decoded['shard']['testsRan'])->toBeInt()
        ->and($decoded['shard']['testsCount'])->toBeInt();
})->skipOnWindows();
