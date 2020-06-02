<?php

use Symfony\Component\Process\Process;

$run = function (string $target) {
    $process = new Process(['php', 'bin/pest', $target], dirname(__DIR__, 2));

    $process->run();

    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
};

test('allows to run a single test', function () use ($run) {
    assertStringContainsString(<<<EOF
   PASS  Tests\Fixtures\DirectoryWithTests\ExampleTest
  ✓ it example

  Tests:  1 passed
EOF, $run('tests/Fixtures/DirectoryWithTests/ExampleTest.php'));
});

test('allows to run a directory', function () use ($run) {
    assertStringContainsString(<<<EOF
   PASS  Tests\Fixtures\DirectoryWithTests\ExampleTest
  ✓ it example

   PASS  Tests\Fixtures\ExampleTest
  ✓ it example

  Tests:  2 passed
EOF, $run('tests/Fixtures'));
});

it('has ascii chars (decorated printer)', function () {
    $process = new Process([
        'php',
        './bin/pest',
        'tests/Fixtures/DirectoryWithTests/ExampleTest.php',
    ], dirname(__DIR__, 2));

    $process->run();
    $output = $process->getOutput();
    assertStringContainsString(<<<EOF
  \e[30;42;1m PASS \e[39;49;22m\e[39m Tests\Fixtures\DirectoryWithTests\ExampleTest\e[39m
  \e[32;1m✓\e[39;22m\e[39m \e[2mit example\e[22m\e[39m

  \e[37;1mTests:  \e[39;22m\e[32;1m1 passed\e[39;22m
EOF, $output);
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

    assertStringContainsString(<<<EOF
   PASS  Tests\Fixtures\DirectoryWithTests\ExampleTest
  ✓ \e[2mit example\e[22m

  Tests:  1 passed
EOF, $output);
});
