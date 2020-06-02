<?php

use Symfony\Component\Process\Process;

$run = function (string $target) {
    $process = new Process(['php', 'bin/pest', $target], dirname(__DIR__, 2));

    $process->run();

    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
};

test('allows to run a single test', function () use ($run) {
    $output = $run('tests/Fixtures/DirectoryWithTests/ExampleTest.php');
    assertStringContainsString('  PASS  Tests\Fixtures\DirectoryWithTests\ExampleTest', $output);
    assertStringContainsString('  ✓ it example 1', $output);
    assertStringContainsString('  Tests:  1 passed', $output);
});

test('allows to run a directory', function () use ($run) {
    $output = $run('tests/Fixtures');
    assertStringContainsString('  PASS  Tests\Fixtures\DirectoryWithTests\ExampleTest', $output);
    assertStringContainsString('  ✓ it example 1', $output);
    assertStringContainsString('  PASS  Tests\Fixtures\ExampleTest', $output);
    assertStringContainsString('  ✓ it example 2', $output);
    assertStringContainsString('  Tests:  2 passed', $output);
});

it('has ascii chars (decorated printer)', function () {
    $process = new Process([
        'php',
        './bin/pest',
        'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
    ], dirname(__DIR__, 2));

    $process->run();
    $output = $process->getOutput();
    assertStringContainsString("  \e[30;42;1m PASS \e[39;49;22m\e[39m Tests\Fixtures\DirectoryWithTests\ExampleTest\e[39m", $output);
    assertStringContainsString("  \e[32;1m✓\e[39;22m\e[39m \e[2mit example 1\e[22m\e[39m", $output);
    assertStringContainsString("  \e[37;1mTests:  \e[39;22m\e[32;1m1 passed\e[39;22m", $output);
});

it('disable decorating printer when colors is set to never', function () {
    $process = new Process([
        'php',
        './bin/pest',
        '--colors=never',
        'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
    ], dirname(__DIR__, 2));
    $process->run();
    $output = $process->getOutput();

    assertStringContainsString('  PASS  Tests\Fixtures\DirectoryWithTests\ExampleTest', $output);
    assertStringContainsString("  ✓ \e[2mit example 1\e[22m", $output);
    assertStringContainsString('  Tests:  1 passed', $output);
});
