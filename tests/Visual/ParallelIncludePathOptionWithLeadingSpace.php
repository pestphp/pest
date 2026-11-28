<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('parallel reads an include path option with a leading space as serial phpunit does', function (): void {
    $script = <<<'PHP_WRAP'
    require "vendor/autoload.php";
    $arguments = ["--include-path=first", " --include-path=lib"];
    $serial = (new PHPUnit\TextUI\CliArguments\Builder(PHPUnit\Event\Facade::emitter()))->fromParameters(["phpunit", ...$arguments])->includePath();
    $handler = new Pest\Plugins\Parallel\Handlers\IncludePath;
    $forwarded = $handler->handleArguments($arguments);
    echo json_encode(["serial" => $serial, "forwarded" => $forwarded, "worker" => $handler->handleWorkerArguments([])]);
    PHP_WRAP;

    $process = new Process(['php', '-r', $script], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(json_decode($process->getOutput(), true))->toBe([
            'serial' => 'lib',
            'forwarded' => ['--include-path=lib'],
            'worker' => ['--include-path=lib'],
        ]);
});
