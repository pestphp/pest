<?php

test('unexpected output', function (array $arguments) {
    $snapshot = __DIR__.'/../.snapshots/unexpected-output.txt';

    if (in_array('--parallel', $arguments)) {
        $snapshot = __DIR__.'/../.snapshots/unexpected-output-parallel.txt';
    }

    $output = function () use ($arguments) {
        $process = (new Symfony\Component\Process\Process(
            array_merge(['php', 'bin/pest', 'tests/Fixtures/UnexpectedOutput.php'], $arguments),
            null,
            ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'COLLISION_TEST' => true]
        ));

        $process->run();

        return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $process->getOutput());
    };

    if (getenv('REBUILD_SNAPSHOTS')) {
        $outputContent = explode("\n", $output());
        array_pop($outputContent);
        array_pop($outputContent);
        array_pop($outputContent);

        if (in_array('--parallel', $arguments)) {
            array_pop($outputContent);
            array_pop($outputContent);
        }

        file_put_contents($snapshot, implode("\n", $outputContent));

        $this->markTestSkipped('Snapshot rebuilt.');
    }

    expect($output())
        ->toContain(file_get_contents($snapshot));
})->with([
    [['']],
    [['--parallel']],
])->skipOnWindows();
