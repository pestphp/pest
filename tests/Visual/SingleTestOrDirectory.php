<?php

use Symfony\Component\Process\Process;

$run = function (string $target) {
    $process = new Process(['./bin/pest', $target], dirname(__DIR__, 2));

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
