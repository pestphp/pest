<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('runs with every supported order', function (string $order): void {
    $process = new Process(
        ['php', 'bin/pest', 'tests/Fixtures/DirectoryWithTests/ExampleTest.php', '--order-by='.$order],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and(removeAnsiEscapeSequences($process->getOutput()))->toContain('1 passed');
})->with([
    'default',
    'defects',
    'duration',
    'duration-ascending',
    'duration-descending',
    'random',
    'reverse',
    'size',
    'size-ascending',
    'size-descending',
]);
