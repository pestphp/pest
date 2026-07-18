<?php

use Symfony\Component\Process\Process;

test('collision', function (array $arguments): void {
    $output = function () use ($arguments): ?string {
        $process = (new Process(
            array_merge(['php', 'bin/pest', 'tests/Fixtures/CollisionTest.php'], $arguments),
            null,
            ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'COLLISION_TEST' => true, 'PAO_DISABLE' => '1']
        ));

        $process->run();

        return removeAnsiEscapeSequences($process->getOutput());
    };

    $outputContent = explode("\n", (string) $output());
    array_pop($outputContent);
    array_pop($outputContent);
    array_pop($outputContent);

    if (in_array('--parallel', $arguments)) {
        array_pop($outputContent);
        array_pop($outputContent);
    }

    expect(implode("\n", $outputContent))->toMatchSnapshot();
})->with([
    [['']],
    // [['--parallel']],
])->skipOnWindows();
