<?php

use Symfony\Component\Process\Process;

$run = function (int $exitCode, array $arguments, array $environment = []): string {
    $process = new Process(
        ['php', 'bin/pest', '--parallel', '--processes=1', '--order-by=default', '--fail-on-deprecation', ...$arguments, 'tests/Fixtures/Suites/ParallelStopOn'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1', ...$environment],
    );

    $process->run();

    $output = (string) removeAnsiEscapeSequences($process->getOutput());

    expect($process->getExitCode())->toBe($exitCode, $process->getErrorOutput().$output)
        ->and($process->getErrorOutput())->toBeEmpty()
        ->and($output)->toMatch('/Tests:\s+\d+ [^\r\n]+\(\d+ assertions\)/');

    return $output;
};

$outcomes = [
    'failure' => ['failure', 'fails', 'failed', 1],
    'defect' => ['defect', 'fails', 'failed', 1],
    'error' => ['error', 'errors', 'failed', 2],
    'risky' => ['risky', 'is risky', 'risky', 1],
    'incomplete' => ['incomplete', 'is incomplete', 'incomplete', 0],
    'warning' => ['warning', 'warns', 'warning', 0],
    'notice' => ['notice', 'notices', 'notice', 0],
    'deprecation' => ['deprecation', 'deprecates', 'deprecated', 1],
    'skipped' => ['skipped', 'is skipped', 'skipped', 0],
];

test('parallel stop-on fixtures run completely without a stop flag', function (string $flag, string $name, string $outcome, int $exitCode) use ($run): void {
    $output = $run($exitCode, ['--filter', "($name|passes)"]);
    $plural = in_array($outcome, ['warning', 'notice'], true) ? $outcome.'s' : $outcome;

    expect($output)->toContain("Tests:    2 $plural, 10 passed");
})->with(array_diff_key($outcomes, ['defect' => true]))->skipOnWindows();

test('parallel honors stop-on flags with worker recycling', function (string $flag, string $name, string $outcome, int $exitCode, int $batchSize) use ($run): void {
    $output = $run($exitCode, ['--filter', "($name|passes)", "--stop-on-$flag", "--max-batch-size=$batchSize"]);

    expect($output)->toContain("Tests:    1 $outcome (")
        ->not->toMatch('/\d+ passed/');
})->with($outcomes)->with([0, 1])->skipOnWindows();

test('parallel honors numeric stop-on thresholds', function (string $flag, string $name, string $outcome, int $exitCode, int $batchSize) use ($run): void {
    $output = $run($exitCode, ['--filter', "($name|passes)", "--stop-on-$flag=2", "--max-batch-size=$batchSize"]);
    $plural = in_array($outcome, ['warning', 'notice'], true) ? $outcome.'s' : $outcome;

    expect($output)->toContain("Tests:    2 $plural (")
        ->not->toMatch('/\d+ passed/');
})->with($outcomes)->with([0, 1])->skipOnWindows();

test('parallel stop-on-defect includes errors warnings and risky tests', function (string $name, string $outcome, int $exitCode) use ($run): void {
    $output = $run($exitCode, ['--filter', "($name|passes)", '--stop-on-defect', '--max-batch-size=1']);

    expect($output)->toContain("Tests:    1 $outcome (")
        ->not->toMatch('/\d+ passed/');
})->with([
    ['errors', 'failed', 2],
    ['warns', 'warning', 0],
    ['is risky', 'risky', 1],
])->skipOnWindows();

test('parallel stop-on-defect allows incomplete and skipped tests', function (string $name, string $outcome) use ($run): void {
    $output = $run(0, ['--filter', "($name|passes)", '--stop-on-defect']);

    expect($output)->toContain("Tests:    2 $outcome, 10 passed");
})->with([
    ['is incomplete', 'incomplete'],
    ['is skipped', 'skipped'],
])->skipOnWindows();

test('parallel does not count the same failure twice', function (int $batchSize) use ($run): void {
    $output = $run(1, ['--filter', '(fails|passes)', '--stop-on-failure=3', "--max-batch-size=$batchSize"]);

    expect($output)->toContain('Tests:    2 failed, 10 passed');
})->with([0, 1])->skipOnWindows();

test('parallel counts issues hidden by the progress output', function (string $flag, string $name, string $outcome, int $exitCode) use ($run): void {
    $output = $run($exitCode, ['--filter', "($name|passes)", $flag, '--max-batch-size=1']);

    expect($output)->toContain("Tests:    1 $outcome (")
        ->not->toMatch('/\d+ passed/');
})->with([
    ['--stop-on-warning', 'has combined issues', 'failed', 1],
    ['--stop-on-defect=2', 'has combined issues', 'failed', 1],
    ['--stop-on-warning=2', 'has repeated warnings', 'warning', 0],
    ['--stop-on-notice=2', 'has repeated notifications', 'notice', 0],
])->skipOnWindows();

test('parallel stops only on the requested deprecation message', function (string $message, int $count) use ($run): void {
    $output = $run(1, ['--filter', '(deprecates|passes)', "--stop-on-deprecation=$message", '--max-batch-size=1']);

    expect($output)->toContain("Tests:    $count deprecated");

    if ($count === 1) {
        expect($output)->not->toMatch('/\d+ passed/');
    } else {
        expect($output)->toContain('10 passed');
    }
})->with([
    ['user deprecation', 1],
    ['another deprecation', 2],
])->skipOnWindows();

test('parallel honors stop-on flags from XML', function () use ($run): void {
    $output = $run(1, ['--configuration=tests/Fixtures/Suites/ParallelStopOn.xml', '--filter', '(fails|passes)', '--max-batch-size=1']);

    expect($output)->toContain('Tests:    1 failed (')
        ->not->toMatch('/\d+ passed/');
})->skipOnWindows();

test('parallel CLI thresholds override XML stop-on flags', function () use ($run): void {
    $output = $run(1, ['--configuration=tests/Fixtures/Suites/ParallelStopOn.xml', '--filter', '(fails|passes)', '--stop-on-failure=2']);

    expect($output)->toContain('Tests:    2 failed (')
        ->not->toMatch('/\d+ passed/');
})->skipOnWindows();

test('parallel clamps empty and non-positive thresholds like PHPUnit', function (string $threshold) use ($run): void {
    $output = $run(1, ['--filter', '(fails|passes)', "--stop-on-failure=$threshold"]);

    expect($output)->toContain('Tests:    1 failed (')
        ->not->toMatch('/\d+ passed/');
})->with(['', '0', '-2'])->skipOnWindows();

test('parallel counts a shared issue only once across recycled workers', function (string $flag, string $name, string $outcome) use ($run): void {
    $output = $run(0, ['--filter', "($name|passes)", "--stop-on-$flag=2", '--max-batch-size=1']);

    expect($output)->toMatch('/Tests:\s+\d+ '.$outcome.'s?, 10 passed/');
})->with([
    ['warning', 'shares warning', 'warning'],
    ['defect', 'shares warning', 'warning'],
    ['notice', 'shares notice', 'notice'],
])->skipOnWindows();

test('parallel combines stop-on counts from different workers and cleans up their files', function () use ($run): void {
    $directory = sys_get_temp_dir().'/pest-stop-on-'.bin2hex(random_bytes(8));
    mkdir($directory);

    try {
        $output = $run(
            1,
            ['--processes=2', '--stop-on-failure=2', '--filter', '(fails|passes)', '--tmp-dir='.$directory],
            ['PEST_TEST_STOP_BARRIER' => $directory],
        );

        expect($output)->toContain('Tests:    2 failed')
            ->not->toContain('10 passed')
            ->not->toContain('did not reach the barrier')
            ->and(glob($directory.'/worker-*'))->toHaveCount(2)
            ->and(glob($directory.'/paratest_*'))->toBeEmpty();
    } finally {
        foreach (glob($directory.'/worker-*') as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
})->skipOnWindows();
