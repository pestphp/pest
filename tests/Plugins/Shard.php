<?php

use Symfony\Component\Process\Process;

$pest = function (string $path, array $extraArgs = []): Process {
    $process = new Process(
        ['php', 'bin/pest', $path, ...$extraArgs],
        dirname(__DIR__, 2),
    );
    $process->run();

    return $process;
};

$shardsPath = dirname(__DIR__).'/.pest/shards.json';

afterAll(function () use ($shardsPath) {
    if (file_exists($shardsPath)) {
        unlink($shardsPath);
    }
});

test('shard discovers tests in standard directories', function () use ($pest) {
    $process = $pest('tests/Fixtures/DirectoryWithTests/', ['--list-tests']);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();

    expect($output)->toContain('ExampleTest::')
        ->toContain(' - P\Tests\\');
})->skipOnWindows();

test('shard includes standard directory tests in shard count', function () use ($pest) {
    $process = $pest('tests/Fixtures/DirectoryWithTests/', ['--shard=1/1']);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();

    expect($output)->toContain('1 file ran, out of 1');
})->skipOnWindows();

test('shard distributes standard directory tests across shards', function () use ($pest) {
    $process1 = $pest('tests/Fixtures/DirectoryWithTests/', ['--shard=1/2']);
    $process2 = $pest('tests/Fixtures/DirectoryWithTests/', ['--shard=2/2']);

    expect($process1->getExitCode())->toBe(0)
        ->and($process2->getExitCode())->toBe(0);

    $output1 = $process1->getOutput();
    $output2 = $process2->getOutput();

    // 1 file total — first shard gets it, second gets 0
    expect($output1)->toContain('1 file ran, out of 1')
        ->and($output2)->toContain('0 files ran, out of 1');
})->skipOnWindows();

test('update-shards records timings for standard directory tests', function () use ($pest, $shardsPath) {
    $process = $pest('tests/Fixtures/DirectoryWithTests/', ['--update-shards']);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();

    expect($output)->toContain('shards.json updated with timings for 1 test class');

    expect($shardsPath)->toBeFile();
    $data = json_decode(file_get_contents($shardsPath), true);
    $keys = array_keys($data['timings']);

    expect($keys)->each->toStartWith('Tests\\');
})->skipOnWindows();

test('shard discovers tests in non-standard directories', function () use ($pest) {
    $process = $pest('tests-external/Shard/', ['--list-tests']);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();

    // Identifiers must NOT start with Tests\ since fixtures are in tests-external/
    expect($output)->toContain('InvoiceTest::')
        ->toContain('UnitTest::')
        ->not->toContain(' - P\Tests\\');
})->skipOnWindows();

test('shard includes non-standard directory tests in shard count', function () use ($pest) {
    $process = $pest('tests-external/Shard/', ['--shard=1/1']);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();

    // Both test files must be discovered and sharded
    expect($output)->toContain('2 files ran, out of 2');
})->skipOnWindows();

test('shard distributes non-standard directory tests across shards', function () use ($pest) {
    $process1 = $pest('tests-external/Shard/', ['--shard=1/2']);
    $process2 = $pest('tests-external/Shard/', ['--shard=2/2']);

    expect($process1->getExitCode())->toBe(0)
        ->and($process2->getExitCode())->toBe(0);

    $output1 = $process1->getOutput();
    $output2 = $process2->getOutput();

    // Each shard gets 1 file, total is 2
    expect($output1)->toContain('1 file ran, out of 2')
        ->and($output2)->toContain('1 file ran, out of 2');
})->skipOnWindows();

test('update-shards records timings for non-standard directory tests', function () use ($pest, $shardsPath) {
    $process = $pest('tests-external/Shard/', ['--update-shards']);

    expect($process->getExitCode())->toBe(0);

    $output = $process->getOutput();

    expect($output)->toContain('shards.json updated with timings for 2 test class');

    // Verify the shards.json contains the non-standard identifiers
    expect($shardsPath)->toBeFile();
    $data = json_decode(file_get_contents($shardsPath), true);
    $keys = array_keys($data['timings']);

    expect($keys)->each->not->toStartWith('Tests\\');
})->skipOnWindows();
