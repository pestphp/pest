<?php

use Symfony\Component\Process\Process;

test('visual snapshot of help command output', function (): void {
    $output = function (): ?string {
        $process = (new Process(['php', 'bin/pest', '--help'], null, ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1']));

        $process->run();

        return removeAnsiEscapeSequences($process->getOutput());
    };

    expect($output())->toMatchSnapshot();
})->skipOnWindows();
