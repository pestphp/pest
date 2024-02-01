<?php

test('collision', function (array $arguments) {
    $output = function () use ($arguments) {
        $process = (new Symfony\Component\Process\Process(
            array_merge(['php', 'bin/pest', 'tests/Fixtures/CollisionTest.php'], $arguments),
            null,
            ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'COLLISION_TEST' => true]
        ));

        $process->run();

        return removeAnsiEscapeSequences($process->getOutput());
    };

    $outputContent = explode("\n", $output());
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
